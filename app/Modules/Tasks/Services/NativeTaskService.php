<?php

namespace Modules\Tasks\Services;

use App\Helpers\TaskNav;
use App\Jobs\SendNotificationEmailJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Services\NotificationService;
use Modules\Security\Models\Role;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Tag;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskActivityLog;
use Modules\Tasks\Models\TaskChecklist;
use Modules\Tasks\Models\TaskChecklistItem;
use Modules\Tasks\Models\TaskComment;
use Modules\Tasks\Models\TaskDependency;
use Modules\Tasks\Models\TaskStakeholder;
use Modules\Tasks\Support\TaskLifecycle;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Services\WorkflowManager;

/**
 * The single place task mutations should go through, whether they originate
 * from the REST API (Modules\Tasks\Controllers\NativeTaskController) or the
 * Livewire UI (App\Livewire\TaskDashboard, App\Livewire\TaskDetail). Before
 * these methods existed, each entry point re-implemented the same Eloquent
 * writes independently and only the API's create/bulk-status paths wrote a
 * TaskActivityLog entry — meaning the audit trail silently missed every
 * edit made through the UI. Centralizing here fixes that for good instead
 * of bolting logging onto each caller separately.
 */
class NativeTaskService
{
    public function createTask(array $data, ?Employee $creator = null): Task
    {
        $data['created_by'] = $data['created_by'] ?? $creator?->id;
        $data['type'] = $data['type'] ?? 'task';

        // Default status state if provided
        if (isset($data['current_state_id'])) {
            $state = WorkflowState::find($data['current_state_id']);
            if ($state && $state->workflow_id && empty($data['workflow_id'])) {
                $data['workflow_id'] = $state->workflow_id;
            }
        }

        $task = Task::create($data);

        // Assignees
        if (! empty($data['assignee_ids'])) {
            foreach ($data['assignee_ids'] as $employeeId) {
                $task->assignments()->create([
                    'employee_id' => $employeeId,
                    'assigned_by' => $creator?->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        // Stakeholders
        if (! empty($data['watchers'])) {
            foreach ($data['watchers'] as $watcherId) {
                TaskStakeholder::create([
                    'task_id' => $task->id,
                    'employee_id' => $watcherId,
                    'role' => 'watcher',
                    'assigned_by' => $creator?->id,
                ]);
            }
        }

        // Tags (ids and/or create-by-name payloads)
        $tagInput = $this->normalizeTagInput($data['tag_ids'] ?? [], $data['tags'] ?? []);
        if ($tagInput !== []) {
            $this->syncTags($task, $tagInput, $creator);
        }

        // Activity log
        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $creator?->id,
            'action' => 'task_created',
            'new_value' => $task->title,
        ]);

        // If the task is created directly in an "active" state, mark the work
        // start time immediately (used by the status timeline UI).
        if ($task->started_at === null && $task->current_state_id) {
            $initialState = WorkflowState::find($task->current_state_id);
            if ($initialState && $initialState->type === 'active') {
                $task->update(['started_at' => now()]);
            }
        }

        $fresh = $task->fresh(['subtasks', 'assignees', 'checklists', 'dependencies', 'stakeholders', 'project.employees', 'creator', 'tags']);
        $this->notifyTaskCreated($fresh, $creator);

        return $fresh;
    }

    public function createSubtask(Task $parentTask, array $data, ?Employee $creator = null): Task
    {
        $data['parent_id'] = $parentTask->id;
        $data['project_id'] = $parentTask->project_id;
        $data['workflow_id'] = $parentTask->workflow_id;

        return $this->createTask($data, $creator);
    }

    public function addChecklist(Task $task, string $title, array $items = []): TaskChecklist
    {
        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => $title,
        ]);

        foreach ($items as $index => $itemTitle) {
            TaskChecklistItem::create([
                'checklist_id' => $checklist->id,
                'title' => is_array($itemTitle) ? ($itemTitle['title'] ?? '') : $itemTitle,
                'sort_order' => $index,
            ]);
        }

        return $checklist->fresh(['items']);
    }

    public function toggleChecklistItem(TaskChecklistItem $item, bool $completed, ?Employee $employee = null): TaskChecklistItem
    {
        $item->update([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $employee?->id : null,
        ]);

        return $item;
    }

    public function addDependency(Task $task, Task $dependsOnTask, string $type = 'blocks'): TaskDependency
    {
        return TaskDependency::firstOrCreate([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
            'type' => $type,
        ]);
    }

    public function bulkUpdateStatus(array $taskUuids, int $stateId, ?Employee $employee = null): int
    {
        $tasks = Task::whereIn('uuid', $taskUuids)->get();
        $updatedCount = 0;

        $state = WorkflowState::find($stateId);
        $now = now();
        $completedAt = $this->isCompletedWorkflowState($state) ? $now : null;

        foreach ($tasks as $task) {
            // Employees cannot mark Done at all.
            if ($state && $this->isCompletedWorkflowState($state) && ! $this->isManager($employee)) {
                continue;
            }
            if ($state && $this->isCompletedWorkflowState($state) && $this->needsReviewApproval($task) && ! $this->isApprovedForCompletion($task) && ! $this->isManager($employee)) {
                continue;
            }

            $durationSeconds = app(TaskLifecycle::class)->currentStatusSeconds($task);
            $startedAt = null;
            if ($state && $state->type === 'active' && $task->started_at === null) {
                $startedAt = $now;
            }

            $oldStateId = $task->current_state_id;
            $fromState = $task->currentState;
            $wasCompleted = $task->completed_at !== null;
            $payload = ['current_state_id' => $stateId];
            if ($state) {
                $payload['completed_at'] = $completedAt;
            }
            if ($startedAt) {
                $payload['started_at'] = $startedAt;
            }
            $task->update($payload);
            $updatedCount++;

            TaskActivityLog::create([
                'task_id' => $task->id,
                'employee_id' => $employee?->id,
                'action' => 'bulk_status_changed',
                'field' => 'current_state_id',
                'old_value' => (string) $oldStateId,
                'new_value' => (string) $stateId,
                'metadata' => [
                    'duration_seconds' => $durationSeconds,
                ],
            ]);

            $fresh = $task->fresh(['currentState', 'assignees']);
            $this->notifyIfEnteredReview($fresh, $employee, $fromState, $state);

            if (! $wasCompleted && $fresh?->completed_at !== null) {
                SendNotificationEmailJob::dispatchNotify('task_completed', $fresh->id);
            }
        }

        return $updatedCount;
    }

    public function archiveTask(Task $task, ?Employee $employee = null): Task
    {
        $task->update(['archived_at' => now()]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $employee?->id,
            'action' => 'task_archived',
        ]);

        return $task;
    }

    public function approveTask(Task $task, Employee $reviewer, ?string $note = null): ?TaskStakeholder
    {
        $stakeholder = $task->reviewers()->where('employee_id', $reviewer->id)->first();

        if (! $stakeholder) {
            if (! $this->isManager($reviewer)) {
                return null;
            }

            $stakeholder = TaskStakeholder::create([
                'task_id' => $task->id,
                'employee_id' => $reviewer->id,
                'role' => 'reviewer',
                'assigned_by' => $reviewer->id,
            ]);
        }

        $stakeholder->update([
            'approval_status' => 'approved',
            'approval_note' => $note,
            'approved_at' => now(),
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $reviewer->id,
            'action' => 'task_approved',
            'new_value' => $note,
        ]);

        $task->unsetRelation('reviewers');
        $doneState = $this->completedStateFor($task);
        if ($doneState && (int) $task->current_state_id !== (int) $doneState->id) {
            $this->moveToState($task, $doneState->id, $reviewer);
        }

        $this->notifyAssignees(
            $task,
            $reviewer,
            'Approved: '.($task->title ?: 'Task'),
            $reviewer->name.' approved this task. It is now complete.',
            'task_approved',
        );

        return $stakeholder;
    }

    public function requestChanges(Task $task, Employee $reviewer, ?string $note = null): ?TaskStakeholder
    {
        $stakeholder = $task->reviewers()->where('employee_id', $reviewer->id)->first();

        if (! $stakeholder) {
            if (! $this->isManager($reviewer)) {
                return null;
            }

            $stakeholder = TaskStakeholder::create([
                'task_id' => $task->id,
                'employee_id' => $reviewer->id,
                'role' => 'reviewer',
                'assigned_by' => $reviewer->id,
            ]);
        }

        $stakeholder->update([
            'approval_status' => 'changes_requested',
            'approval_note' => $note,
            'approved_at' => null,
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $reviewer->id,
            'action' => 'changes_requested',
            'new_value' => $note,
        ]);

        $returnState = $this->activeStateFor($task);
        if ($returnState && (int) $task->current_state_id !== (int) $returnState->id) {
            $this->moveToState($task, $returnState->id, $reviewer);
        }

        $this->notifyAssignees(
            $task,
            $reviewer,
            'Changes requested: '.($task->title ?: 'Task'),
            trim($reviewer->name.' requested changes'.($note ? ': '.$note : '.')),
            'task_changes_requested',
        );

        return $stakeholder;
    }

    public function isApprovedForCompletion(Task $task): bool
    {
        $reviewers = $task->reviewers;

        if ($reviewers->isEmpty()) {
            return false;
        }

        return $reviewers->contains(fn ($reviewer) => $reviewer->approval_status === 'approved');
    }

    public function needsReviewApproval(Task $task): bool
    {
        $currentName = strtolower((string) ($task->currentState?->name ?? ''));
        $fromReview = str_contains($currentName, 'review');

        return $fromReview || $task->reviewers()->exists() || $task->isLinkedToConversationSession();
    }

    /**
     * Generic multi-field update. `status` is handled specially because
     * it's a virtual attribute (Task::setStatusAttribute() resolves it to a
     * `current_state_id` via a WorkflowState lookup) rather than a plain
     * fillable column, so it can't go through fill() like the others.
     */
    public function updateFields(Task $task, array $data, ?Employee $actor = null): Task
    {
        $trackedFields = [
            'title', 'description', 'priority', 'due_date', 'start_date',
            'estimated_hours', 'actual_hours', 'project_id', 'current_state_id',
        ];

        $needsApproval = $this->needsReviewApproval($task);
        $fromState = $task->currentState;
        $wasCompleted = $task->completed_at !== null;
        $changes = [];
        $durationSecondsForStatusChange = null;

        foreach ($trackedFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $task->{$field};
            $oldValue = $old instanceof \BackedEnum ? $old->value : (is_null($old) ? null : (string) $old);
            $newValue = is_null($data[$field]) ? null : (string) $data[$field];

            if ($oldValue !== $newValue) {
                $changes[$field] = [$oldValue, $newValue];
            }
        }

        if (array_key_exists('current_state_id', $changes) && $durationSecondsForStatusChange === null) {
            $durationSecondsForStatusChange = app(TaskLifecycle::class)->currentStatusSeconds($task);
        }

        if (array_key_exists('status', $data)) {
            $oldStatus = $task->status?->value;

            // Compute the current-status dwell time before we mutate
            // `current_state_id` via Task::setStatusAttribute().
            if ($durationSecondsForStatusChange === null) {
                $raw = (string) $data['status'];
                $normalized = match ($raw) {
                    'to_do', 'todo' => TaskStatus::Todo->value,
                    'in_progress' => TaskStatus::InProgress->value,
                    'review', 'code_review', 'in_review' => TaskStatus::Review->value,
                    'done' => TaskStatus::Done->value,
                    'cancelled', 'canceled' => TaskStatus::Cancelled->value,
                    default => $raw,
                };

                if ($oldStatus !== $normalized) {
                    $durationSecondsForStatusChange = app(TaskLifecycle::class)->currentStatusSeconds($task);
                }
            }

            $task->status = $data['status'];
            unset($data['status']);

            // status is derived from the (lazy-loaded, cached) `currentState`
            // relation. The mutator above only changes `current_state_id`,
            // so the cached relation is now stale and status would still
            // read back as $oldStatus without dropping it first.
            $task->unsetRelation('currentState');

            if ($oldStatus !== $task->status?->value) {
                $changes['status'] = [$oldStatus, $task->status?->value];
            }
        }

        $task->fill($data);

        if (array_key_exists('status', $changes) || array_key_exists('current_state_id', $changes)) {
            $task->unsetRelation('currentState');
            $task->completed_at = $this->isCompletedWorkflowState($task->currentState) ? now() : null;
        }

        if ($changes === [] && ! $task->isDirty()) {
            return $task;
        }

        if (array_key_exists('status', $changes) || array_key_exists('current_state_id', $changes)) {
            $task->unsetRelation('currentState');
            $newState = $task->currentState;

            // Employees cannot mark Done at all.
            if ($newState && $this->isCompletedWorkflowState($newState) && ! $this->isManager($actor)) {
                return $task->fresh(['assignees', 'checklists.items', 'project', 'currentState']);
            }

            if ($newState && $this->isCompletedWorkflowState($newState) && $needsApproval && ! $this->isApprovedForCompletion($task) && ! $this->isManager($actor)) {
                return $task->fresh(['assignees', 'checklists.items', 'project', 'currentState']);
            }

            if ($newState && $newState->type === 'active' && $task->started_at === null) {
                $task->started_at = now();
            }
        }

        $task->save();

        foreach ($changes as $field => [$oldValue, $newValue]) {
            $metadata = null;
            if ($durationSecondsForStatusChange !== null && in_array($field, ['status', 'current_state_id'], true)) {
                $metadata = [
                    'duration_seconds' => (int) $durationSecondsForStatusChange,
                ];
            }

            TaskActivityLog::create([
                'task_id' => $task->id,
                'employee_id' => $actor?->id,
                'action' => 'field_updated',
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'metadata' => $metadata,
            ]);
        }

        $fresh = $task->fresh(['assignees', 'checklists.items', 'project', 'currentState']);
        $this->notifyIfEnteredReview($fresh, $actor, $fromState, $fresh?->currentState);

        if (! $wasCompleted && $fresh?->completed_at !== null) {
            SendNotificationEmailJob::dispatchNotify('task_completed', $fresh->id);
        }

        return $fresh;
    }

    /**
     * Replace a task's assignees wholesale (used by both the single-task
     * "assignee" picker and the bulk-assign action).
     */
    public function updateAssignees(Task $task, array $employeeIds, ?Employee $actor = null): Task
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        $oldIds = $task->assignees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();
        $employeeIds = array_map('intval', $employeeIds);
        $sortedOld = $oldIds;
        $sortedNew = $employeeIds;
        sort($sortedOld);
        sort($sortedNew);
        if ($sortedOld === $sortedNew) {
            return $task;
        }

        $task->assignments()->delete();

        foreach ($employeeIds as $employeeId) {
            $task->assignments()->create([
                'employee_id' => $employeeId,
                'status' => 'accepted',
                'role' => 'assignee',
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
            ]);
        }

        if ($oldIds !== $employeeIds) {
            TaskActivityLog::create([
                'task_id' => $task->id,
                'employee_id' => $actor?->id,
                'action' => 'assignees_changed',
                'old_value' => implode(',', $oldIds),
                'new_value' => implode(',', $employeeIds),
            ]);
        }

        $fresh = $task->fresh(['assignees']);
        $added = array_values(array_diff($employeeIds, $oldIds));
        $this->notifyAssigneesAdded($fresh, $added, $actor);

        return $fresh;
    }

    /**
     * Sync task tags from a mix of ids, names, or {id?, name, color?} payloads.
     * Missing names are created in the task's workspace (ClickUp-style create-on-type).
     *
     * @param  list<int|string|array{id?: int, name?: string, color?: string}>  $tagsInput
     */
    public function syncTags(Task $task, array $tagsInput, ?Employee $actor = null): Task
    {
        $resolvedIds = $this->resolveTagIds($task, $tagsInput);
        $oldIds = $task->tags()->pluck('tags.id')->map(fn ($id) => (int) $id)->all();
        $sortedOld = $oldIds;
        $sortedNew = $resolvedIds;
        sort($sortedOld);
        sort($sortedNew);

        if ($sortedOld === $sortedNew) {
            return $task->loadMissing('tags');
        }

        $task->tags()->sync($resolvedIds);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $actor?->id,
            'action' => 'tags_changed',
            'old_value' => implode(',', $oldIds),
            'new_value' => implode(',', $resolvedIds),
        ]);

        return $task->fresh(['tags']);
    }

    /**
     * @param  list<int|string|array{id?: int, name?: string, color?: string}>  $tagsInput
     */
    public function updateTags(Task $task, array $tagsInput, ?Employee $actor = null): Task
    {
        return $this->syncTags($task, $tagsInput, $actor);
    }

    /**
     * @param  list<int|string>  $tagIds
     * @param  list<int|string|array{id?: int, name?: string, color?: string}>  $tags
     * @return list<int|string|array{id?: int, name?: string, color?: string}>
     */
    protected function normalizeTagInput(array $tagIds, array $tags): array
    {
        return array_values(array_merge($tagIds, $tags));
    }

    /**
     * @param  list<int|string|array{id?: int, name?: string, color?: string}>  $tagsInput
     * @return list<int>
     */
    protected function resolveTagIds(Task $task, array $tagsInput): array
    {
        $workspaceId = $task->workspace_id ?? $task->creator?->workspace_id;
        $resolvedIds = [];

        foreach ($tagsInput as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $query = Tag::query()->where('id', (int) $item);
                if ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                }
                $tag = $query->first();
                if ($tag) {
                    $resolvedIds[] = (int) $tag->id;
                }

                continue;
            }

            if (is_array($item)) {
                if (! empty($item['id']) && (is_int($item['id']) || ctype_digit((string) $item['id']))) {
                    $query = Tag::query()->where('id', (int) $item['id']);
                    if ($workspaceId) {
                        $query->where('workspace_id', $workspaceId);
                    }
                    $tag = $query->first();
                    if ($tag) {
                        if (! empty($item['color']) && $tag->color !== $item['color']) {
                            $tag->update(['color' => (string) $item['color']]);
                        }
                        $resolvedIds[] = (int) $tag->id;
                    }

                    continue;
                }

                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $color = (string) ($item['color'] ?? '#6B7280');
                $tag = Tag::query()->firstOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'name' => $name,
                    ],
                    ['color' => $color ?: '#6B7280']
                );
                $resolvedIds[] = (int) $tag->id;

                continue;
            }

            if (is_string($item)) {
                $name = trim($item);
                if ($name === '') {
                    continue;
                }

                $tag = Tag::query()->firstOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'name' => $name,
                    ],
                    ['color' => '#6B7280']
                );
                $resolvedIds[] = (int) $tag->id;
            }
        }

        return array_values(array_unique($resolvedIds));
    }

    /**
     * Move a task to a different workflow state (kanban drag-drop / status
     * dropdown by state id, as opposed to updateFields()'s status-by-name).
     */
    public function moveToState(Task $task, int $stateId, ?Employee $actor = null): Task
    {
        $state = WorkflowState::find($stateId);

        if (! $state) {
            return $task;
        }

        $oldStateId = $task->current_state_id;
        $fromState = $task->currentState;

        if ((int) $oldStateId === $stateId) {
            return $task;
        }

        // Employees cannot mark tasks as Done (completed/closed) regardless of review status.
        if ($this->isCompletedWorkflowState($state) && ! $this->isManager($actor)) {
            return $task;
        }

        if ($this->isCompletedWorkflowState($state) && $this->needsReviewApproval($task) && ! $this->isApprovedForCompletion($task) && ! $this->isManager($actor)) {
            return $task;
        }

        $wasCompleted = $task->completed_at !== null;
        $now = now();
        $durationSeconds = app(TaskLifecycle::class)->currentStatusSeconds($task);
        $startedAt = ($state->type === 'active' && $task->started_at === null) ? $now : null;

        $task->update([
            'current_state_id' => $stateId,
            'workflow_id' => $state->workflow_id,
            'completed_at' => $this->isCompletedWorkflowState($state) ? $now : null,
            'started_at' => $startedAt ?? $task->started_at,
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $actor?->id,
            'action' => 'state_moved',
            'field' => 'current_state_id',
            'old_value' => (string) $oldStateId,
            'new_value' => (string) $stateId,
            'metadata' => [
                'duration_seconds' => $durationSeconds,
            ],
        ]);

        if (! $wasCompleted && $task->completed_at !== null) {
            SendNotificationEmailJob::dispatchNotify('task_completed', $task->id);
        }

        $fresh = $task->fresh(['currentState']);
        $this->notifyIfEnteredReview($fresh, $actor, $fromState, $state);

        return $fresh;
    }

    /**
     * Inline "add task" affordance (e.g. typing a title directly into a
     * kanban column). Delegates to createTask() so it gets the same
     * activity-log entry as every other creation path.
     */
    public function quickCreate(string $title, ?int $stateId, ?Employee $actor = null, array $extra = []): Task
    {
        $title = trim($title);

        $state = $stateId
            ? WorkflowState::find($stateId)
            : app(WorkflowManager::class)->getDefaultState('task');

        return $this->createTask(array_filter([
            'title' => $title,
            'type' => 'task',
            'priority' => $extra['priority'] ?? 'medium',
            'workflow_id' => $state?->workflow_id,
            'current_state_id' => $state?->id,
            'project_id' => $extra['project_id'] ?? null,
            'assignee_ids' => $extra['assignee_ids'] ?? [],
        ], fn ($value) => $value !== null && $value !== []), $actor);
    }

    public function addComment(Task $task, string $content, ?Employee $actor = null): TaskComment
    {
        $comment = $task->comments()->create([
            'employee_id' => $actor?->id,
            'content' => trim($content),
        ]);

        $mentionedIds = $this->parseMentions($content, $task);
        $notificationService = app(NotificationService::class);
        $taskTitle = $task->title ?: 'Task';
        $authorName = $actor?->name ?? 'Someone';
        $preview = Str::limit(trim($comment->content), 120);

        foreach ($mentionedIds as $employeeId) {
            if ($employeeId === $actor?->id) {
                continue;
            }

            $mention = $comment->mentions()->firstOrCreate([
                'employee_id' => $employeeId,
            ]);

            $employee = Employee::with('user')->find($employeeId);

            if (! $employee) {
                continue;
            }

            $notificationService->send(
                title: "Mentioned in task: {$taskTitle}",
                body: "{$authorName} mentioned you in a comment. {$preview}",
                type: 'comment_mention',
                employee: $employee,
                userId: $employee->user_id,
                actionUrl: TaskNav::detailUrlFor($employee->user, $task->id, []),
                metadata: [
                    'task_id' => $task->id,
                    'task_comment_id' => $comment->id,
                    'comment_mention_id' => $mention->id,
                ],
            );

            SendNotificationEmailJob::dispatchNotify('comment_mention', $comment->id, $employeeId);
        }

        return $comment;
    }

    /**
     * Extract @mentioned employee IDs from comment content.
     */
    protected function parseMentions(string $content, Task $task): array
    {
        preg_match_all('/@(\w[\w\s]*?)(?=\s@|\s*$|[.,!?;:\)\]])/', $content, $matches);

        if (empty($matches[1])) {
            preg_match_all('/@\[([^\]]+)\]/', $content, $bracketMatches);
            if (empty($bracketMatches[1])) {
                return [];
            }

            $names = array_map('trim', $bracketMatches[1]);
        } else {
            $names = array_map('trim', $matches[1]);

            preg_match_all('/@\[([^\]]+)\]/', $content, $bracketMatches);
            if (! empty($bracketMatches[1])) {
                $names = array_merge($names, array_map('trim', $bracketMatches[1]));
            }
        }

        $names = array_values(array_filter(array_unique($names)));

        if ($names === []) {
            return [];
        }

        $accessibleIds = $task->accessibleEmployeeIds();
        if ($accessibleIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $accessibleIds)
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhere('name', $name)->orWhere('name', 'like', $name.'%');
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function addChecklistItem(TaskChecklist $checklist, string $title): TaskChecklistItem
    {
        return $checklist->items()->create([
            'title' => trim($title),
            'is_completed' => false,
        ]);
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkUpdatePriority(array $taskIds, string $priority, ?Employee $actor = null): int
    {
        $tasks = Task::whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            $old = $task->priority?->value;
            $task->priority = $priority;
            $task->save();

            TaskActivityLog::create([
                'task_id' => $task->id,
                'employee_id' => $actor?->id,
                'action' => 'bulk_priority_changed',
                'field' => 'priority',
                'old_value' => $old,
                'new_value' => $priority,
            ]);
        }

        return $tasks->count();
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkUpdateDueDate(array $taskIds, ?string $dueDate, ?Employee $actor = null): int
    {
        $tasks = Task::whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            $old = $task->due_date?->format('Y-m-d');
            $task->due_date = $dueDate ?: null;
            $task->save();

            TaskActivityLog::create([
                'task_id' => $task->id,
                'employee_id' => $actor?->id,
                'action' => 'bulk_due_date_changed',
                'field' => 'due_date',
                'old_value' => $old,
                'new_value' => $dueDate ?: null,
            ]);
        }

        return $tasks->count();
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkUpdateAssignee(array $taskIds, ?int $employeeId, ?Employee $actor = null): int
    {
        $tasks = Task::whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            $this->updateAssignees($task, $employeeId ? [$employeeId] : [], $actor);
        }

        return $tasks->count();
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkDelete(array $taskIds, ?Employee $actor = null): int
    {
        $tasks = Task::whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            $this->deleteTask($task, $actor);
        }

        return $tasks->count();
    }

    public function deleteTask(Task $task, ?Employee $actor = null): void
    {
        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $actor?->id,
            'action' => 'task_deleted',
        ]);

        // Keep assignees/checklists so restore is full; soft-delete direct subtasks
        // so they do not linger as orphans while the parent is in trash.
        $task->directSubtasks()->get()->each(fn (Task $subtask) => $subtask->delete());
        $task->delete();
    }

    public function restoreTask(Task $task, ?Employee $actor = null): Task
    {
        $task->restore();

        Task::onlyTrashed()
            ->where('parent_id', $task->id)
            ->get()
            ->each(fn (Task $subtask) => $subtask->restore());

        TaskActivityLog::create([
            'task_id' => $task->id,
            'employee_id' => $actor?->id,
            'action' => 'task_restored',
        ]);

        return $task->fresh() ?? $task;
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkRestore(array $taskIds, ?Employee $actor = null): int
    {
        $tasks = Task::onlyTrashed()->whereIn('id', $taskIds)->get();

        foreach ($tasks as $task) {
            $this->restoreTask($task, $actor);
        }

        return $tasks->count();
    }

    public function forceDeleteTask(Task $task, ?Employee $actor = null): void
    {
        Task::withTrashed()
            ->where('parent_id', $task->id)
            ->get()
            ->each(fn (Task $subtask) => $subtask->forceDelete());

        $task->forceDelete();
    }

    /**
     * Permanently remove soft-deleted tasks older than $days days.
     */
    public function purgeExpiredTrash(int $days = 30): int
    {
        $cutoff = now()->subDays($days);
        $tasks = Task::onlyTrashed()
            ->whereNull('parent_id')
            ->where('deleted_at', '<', $cutoff)
            ->get();

        foreach ($tasks as $task) {
            $this->forceDeleteTask($task);
        }

        return $tasks->count();
    }

    protected function activeStateFor(Task $task): ?WorkflowState
    {
        $states = WorkflowState::query()
            ->when($task->workflow_id, fn ($query) => $query->where('workflow_id', $task->workflow_id))
            ->orderBy('order')
            ->get();

        return $states->firstWhere('type', 'active')
            ?? $states->first(fn (WorkflowState $state) => strcasecmp((string) $state->name, 'In Progress') === 0);
    }

    protected function completedStateFor(Task $task): ?WorkflowState
    {
        $states = WorkflowState::query()
            ->when($task->workflow_id, fn ($query) => $query->where('workflow_id', $task->workflow_id))
            ->orderBy('order')
            ->get();

        return $states->first(fn (WorkflowState $state) => $this->isCompletedWorkflowState($state));
    }

    protected function notifyAssignees(Task $task, Employee $actor, string $title, string $body, string $type): void
    {
        $task->loadMissing('assignees.user');
        $notificationService = app(NotificationService::class);

        foreach ($task->assignees as $assignee) {
            if ((int) $assignee->id === (int) $actor->id) {
                continue;
            }

            $notificationService->send(
                title: $title,
                body: $body,
                type: $type,
                employee: $assignee,
                userId: $assignee->user_id,
                actionUrl: TaskNav::detailUrlFor($assignee->user, $task->id, []),
                metadata: ['task_id' => $task->id],
            );

            SendNotificationEmailJob::dispatchNotify($type, $task->id, $assignee->id);
        }
    }

    protected function notifyIfEnteredReview(Task $task, ?Employee $actor, ?WorkflowState $from, ?WorkflowState $to): void
    {
        if (! $this->isReviewWorkflowState($to) || $this->isReviewWorkflowState($from)) {
            return;
        }

        $task->loadMissing(['reviewers.employee.user', 'assignees']);
        $recipients = $task->reviewers
            ->pluck('employee')
            ->filter();

        if ($recipients->isEmpty()) {
            $recipients = $this->managersFor($task);
            $recipients->loadMissing('user');
        }

        $actorName = $actor?->name ?? 'Someone';
        $notificationService = app(NotificationService::class);

        foreach ($recipients->unique('id') as $recipient) {
            if (! $recipient instanceof Employee) {
                continue;
            }
            if ($actor && (int) $recipient->id === (int) $actor->id) {
                continue;
            }

            $recipient->loadMissing('user');

            $notificationService->send(
                title: 'Ready for review: '.($task->title ?: 'Task'),
                body: $actorName.' moved this task to Review.',
                type: 'review_requested',
                employee: $recipient,
                userId: $recipient->user_id,
                actionUrl: TaskNav::detailUrlFor($recipient->user, $task->id, []),
                metadata: ['task_id' => $task->id],
            );

            SendNotificationEmailJob::dispatchNotify('review_requested', $task->id, $recipient->id, $actor?->id);
        }
    }

    /**
     * @return Collection<int, Employee>
     */
    protected function managersFor(Task $task)
    {
        $privileged = Role::privileged();

        return Employee::query()
            ->when($task->workspace_id, fn ($query) => $query->where('workspace_id', $task->workspace_id))
            ->where(function ($query) use ($privileged) {
                $query->where(function ($roleQuery) use ($privileged) {
                    foreach ($privileged as $term) {
                        $roleQuery->orWhereRaw('LOWER(COALESCE(role, \'\')) LIKE ?', ['%'.$term.'%']);
                    }
                })->orWhereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('slug', $privileged));
            })
            ->get();
    }

    protected function isReviewWorkflowState(?WorkflowState $state): bool
    {
        return $state !== null && str_contains(strtolower((string) $state->name), 'review');
    }

    protected function isCompletedWorkflowState(?WorkflowState $state): bool
    {
        if (! $state) {
            return false;
        }

        if (in_array($state->type, ['completed', 'closed'], true)) {
            return true;
        }

        return strcasecmp((string) $state->name, 'Done') === 0;
    }

    protected function isManager(?Employee $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->isPrivileged();
    }

    /**
     * Email + in-app: assignees get task_assigned; other project members get task_created.
     */
    protected function notifyTaskCreated(Task $task, ?Employee $creator = null): void
    {
        $task->loadMissing(['assignees.user', 'project.employees.user']);
        $notificationService = app(NotificationService::class);
        $creatorName = $creator?->name ?? $task->creator?->name ?? 'Someone';
        $assigneeIds = $task->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($task->assignees as $assignee) {
            if ($creator && (int) $assignee->id === (int) $creator->id) {
                continue;
            }

            $notificationService->send(
                title: 'Task assigned: '.($task->title ?: 'Task'),
                body: "{$creatorName} assigned you to this task.",
                type: 'task_assigned',
                employee: $assignee,
                userId: $assignee->user_id,
                actionUrl: TaskNav::detailUrlFor($assignee->user, $task->id, []),
                metadata: ['task_id' => $task->id],
            );

            SendNotificationEmailJob::dispatchNotify('task_assigned', $task->id, $assignee->id);
        }

        if (! $task->project_id) {
            return;
        }

        $notified = collect($assigneeIds);
        if ($creator) {
            $notified->push((int) $creator->id);
        }
        if ($task->created_by) {
            $notified->push((int) $task->created_by);
        }

        foreach ($task->project?->employees ?? [] as $member) {
            if ($notified->contains((int) $member->id)) {
                continue;
            }

            $notificationService->send(
                title: 'New task: '.($task->title ?: 'Task'),
                body: "{$creatorName} created a task in {$task->project->name}.",
                type: 'task_created',
                employee: $member,
                userId: $member->user_id,
                actionUrl: TaskNav::detailUrlFor($member->user, $task->id, []),
                metadata: ['task_id' => $task->id, 'project_id' => $task->project_id],
            );
        }

        // One job fans out email to all project members (service excludes creator).
        SendNotificationEmailJob::dispatchNotify('task_created', $task->id, $creator?->id);
    }

    /**
     * @param  list<int>  $addedEmployeeIds
     */
    protected function notifyAssigneesAdded(Task $task, array $addedEmployeeIds, ?Employee $actor = null): void
    {
        if ($addedEmployeeIds === []) {
            return;
        }

        $notificationService = app(NotificationService::class);
        $actorName = $actor?->name ?? 'Someone';

        foreach ($addedEmployeeIds as $employeeId) {
            if ($actor && (int) $employeeId === (int) $actor->id) {
                continue;
            }

            $employee = Employee::with('user')->find($employeeId);
            if (! $employee) {
                continue;
            }

            $notificationService->send(
                title: 'Task assigned: '.($task->title ?: 'Task'),
                body: "{$actorName} assigned you to this task.",
                type: 'task_assigned',
                employee: $employee,
                userId: $employee->user_id,
                actionUrl: TaskNav::detailUrlFor($employee->user, $task->id, []),
                metadata: ['task_id' => $task->id],
            );

            SendNotificationEmailJob::dispatchNotify('task_assigned', $task->id, $employee->id);
        }
    }
}
