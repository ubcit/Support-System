<?php

namespace App\Livewire\TaskDetail;

use App\Helpers\TaskNav;
use App\Helpers\TaskQuery;
use App\Livewire\Concerns\AuthorizesActions;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Attachments\Services\AttachmentService;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Tag;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskChecklist;
use Modules\Tasks\Models\TaskChecklistItem;
use Modules\Tasks\Services\NativeTaskService;

class Index extends Component
{
    use AuthorizesActions, WithFileUploads;

    public int|string|null $record = null;

    public ?Task $task = null;

    // Editable fields
    public string $taskTitle = '';

    public string $description = '';

    public ?string $dueDate = null;

    public ?string $startDate = null;

    public string $status = 'to_do';

    public string $priority = 'medium';

    public mixed $projectId = null;

    public array $assignedEmployeeIds = [];

    public array $selectedTagIds = [];

    public string $newTagName = '';

    public string $newTagColor = '#6B7280';

    public ?float $estimatedHours = 0;

    public ?float $actualHours = 0;

    /** Last server values used for dirty-field guards during live poll refresh. */
    public string $syncedTitle = '';

    public string $syncedDescription = '';

    public ?string $syncedDueDate = null;

    public ?string $syncedStartDate = null;

    public ?float $syncedEstimatedHours = 0;

    public ?float $syncedActualHours = 0;

    public ?string $syncedUpdatedAt = null;

    public int $syncedRelationStamp = 0;

    // Interactive component inputs
    public string $newCommentText = '';

    public string $newChecklistName = '';

    public string $newSubtaskTitle = '';

    public $newAttachmentFile = null;

    public string $approvalNote = '';

    public function mount(int|string|null $record = null): void
    {
        $this->record = $record;
        $filters = TaskNav::fromRequest();
        if ($filters !== []) {
            TaskNav::remember($filters);
        }
        if ($record !== null && $record !== '') {
            $this->loadTask();
        }
    }

    protected function actor(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }

    /**
     * Mutations on this page require TaskPolicy::update (tasks.edit + assignee
     * for employees; privileged roles can edit any task).
     */
    protected function authorizeTaskUpdate(): void
    {
        if (! $this->task) {
            throw new AuthorizationException('Task not loaded.');
        }

        $this->authorize('update', $this->task);
    }

    public function loadTask(): void
    {
        $this->hydrateTaskFromServer(preserveDirty: false);
    }

    /**
     * Live poll entry point: reload task when another user changes it,
     * without wiping in-progress title/description/hours edits.
     */
    public function refreshFromServer(): void
    {
        if ($this->record === null || $this->record === '') {
            return;
        }

        $probe = $this->taskProbeQuery()->first();

        if (! $probe) {
            $this->task = null;
            session()->flash('error', 'Task not found.');

            return;
        }

        $updatedAt = $probe->updated_at?->toJSON();
        $stamp = $this->relationStampFromProbe($probe);

        if (
            $this->task
            && $this->syncedUpdatedAt === $updatedAt
            && $this->syncedRelationStamp === $stamp
        ) {
            return;
        }

        $this->hydrateTaskFromServer(preserveDirty: true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Task>
     */
    protected function taskProbeQuery()
    {
        $query = Task::query()->withCount([
            'comments',
            'tags',
            'assignees',
            'attachments',
            'subtasks',
            'checklists',
            'activityLogs',
        ]);

        return is_numeric($this->record)
            ? $query->whereKey((int) $this->record)
            : $query->where('uuid', $this->record);
    }

    protected function relationStampFromProbe(Task $probe): int
    {
        $checklistItemsDone = TaskChecklistItem::query()
            ->whereIn('checklist_id', TaskChecklist::query()->where('task_id', $probe->id)->select('id'))
            ->selectRaw('count(*) as total_count, coalesce(sum(case when is_completed then 1 else 0 end), 0) as done_count')
            ->first();

        return (int) crc32(implode(':', [
            (string) ($probe->comments_count ?? 0),
            (string) ($probe->tags_count ?? 0),
            (string) ($probe->assignees_count ?? 0),
            (string) ($probe->attachments_count ?? 0),
            (string) ($probe->subtasks_count ?? 0),
            (string) ($probe->checklists_count ?? 0),
            (string) ($probe->activity_logs_count ?? 0),
            (string) ($checklistItemsDone->total_count ?? 0),
            (string) ($checklistItemsDone->done_count ?? 0),
        ]));
    }

    protected function hydrateTaskFromServer(bool $preserveDirty): void
    {
        $query = Task::with([
            'project',
            'currentState',
            'assignees.user',
            'tags',
            'checklists.items',
            'subtasks',
            'attachments',
            'comments' => fn ($query) => $query->with('employee.user')->latest(),
            'activityLogs' => fn ($q) => $q->where(function ($q) {
                $q->whereIn('action', ['state_moved', 'bulk_status_changed'])
                    ->orWhere(function ($q) {
                        $q->where('action', 'field_updated')
                            ->whereIn('field', ['status', 'current_state_id']);
                    });
            })->orderBy('created_at'),
            'creator',
            'timeLogs',
            'reviewers.employee',
        ])->withCount([
            'comments',
            'tags',
            'assignees',
            'attachments',
            'subtasks',
            'checklists',
            'activityLogs',
        ]);

        $this->task = is_numeric($this->record)
            ? $query->find((int) $this->record)
            : $query->where('uuid', $this->record)->first();

        if (! $this->task) {
            session()->flash('error', 'Task not found.');

            return;
        }

        $user = auth()->user();
        if ($user && ! $user->can('view', $this->task)) {
            $this->task = null;
            session()->flash('error', 'You do not have access to this task.');

            return;
        }

        $serverTitle = $this->task->title;
        $serverDescription = $this->task->description ?? '';
        $serverDueDate = $this->task->due_date?->format('Y-m-d');
        $serverStartDate = $this->task->start_date?->format('Y-m-d');
        $serverEstimatedHours = (float) ($this->task->estimated_hours ?? 0);
        $serverActualHours = (float) ($this->task->actual_hours ?? 0);

        if (! $preserveDirty || $this->taskTitle === $this->syncedTitle) {
            $this->taskTitle = $serverTitle;
        }
        if (! $preserveDirty || $this->description === $this->syncedDescription) {
            $this->description = $serverDescription;
        }
        if (! $preserveDirty || $this->dueDate === $this->syncedDueDate) {
            $this->dueDate = $serverDueDate;
        }
        if (! $preserveDirty || $this->startDate === $this->syncedStartDate) {
            $this->startDate = $serverStartDate;
        }
        if (! $preserveDirty || (float) $this->estimatedHours === (float) $this->syncedEstimatedHours) {
            $this->estimatedHours = $serverEstimatedHours;
        }
        if (! $preserveDirty || (float) $this->actualHours === (float) $this->syncedActualHours) {
            $this->actualHours = $serverActualHours;
        }

        $this->status = $this->task->status->value;
        $this->priority = $this->task->priority?->value ?? 'medium';
        $this->projectId = $this->task->project_id;
        $this->assignedEmployeeIds = $this->task->assignees->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $this->selectedTagIds = $this->task->tags->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        $this->syncedTitle = $serverTitle;
        $this->syncedDescription = $serverDescription;
        $this->syncedDueDate = $serverDueDate;
        $this->syncedStartDate = $serverStartDate;
        $this->syncedEstimatedHours = $serverEstimatedHours;
        $this->syncedActualHours = $serverActualHours;
        $this->syncedUpdatedAt = $this->task->updated_at?->toJSON();
        $this->syncedRelationStamp = $this->relationStampFromProbe($this->task);
    }

    /**
     * Message-sourced context for the sidebar / description area.
     *
     * @return array{
     *     source: ?string,
     *     customer_request: ?string,
     *     conversation_id: ?int,
     *     conversation_session_id: ?int,
     *     conversation_url: ?string,
     *     can_open_conversation: bool
     * }
     */
    public function messageSourceContext(): array
    {
        $meta = $this->task?->metadata ?? [];
        $source = is_string($meta['source'] ?? null) ? $meta['source'] : null;
        $conversationId = isset($meta['conversation_id']) ? (int) $meta['conversation_id'] : null;
        $sessionId = isset($meta['conversation_session_id']) ? (int) $meta['conversation_session_id'] : null;

        $customerRequest = null;
        $description = (string) ($this->task?->description ?? '');
        if (preg_match('/## Customer request\s*\n(.*)$/s', $description, $matches)) {
            $customerRequest = trim($matches[1]);
        } elseif (in_array($source, ['customer_message', 'boss_command'], true) && $description !== '') {
            $customerRequest = $description;
        }

        $user = auth()->user();
        $canOpen = (bool) ($user && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin());

        $conversationUrl = null;
        if ($canOpen && $conversationId) {
            $conversationUrl = route('conversation-center', array_filter([
                'conversation' => $conversationId,
                'session' => $sessionId,
            ]));
        }

        return [
            'source' => $source,
            'customer_request' => $customerRequest,
            'conversation_id' => $conversationId,
            'conversation_session_id' => $sessionId,
            'conversation_url' => $conversationUrl,
            'can_open_conversation' => $canOpen && $conversationUrl !== null,
        ];
    }

    public function updateTaskTitle(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, ['title' => $this->taskTitle], $this->actor());
        session()->flash('success', 'Title updated');
    }

    public function updatedDescription(): void
    {
        $this->updateTaskDescription();
    }

    public function updateTaskDescription(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, ['description' => $this->description], $this->actor());
    }

    public function updateStatus(string $newStatus): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        $previousStateId = (int) $this->task->current_state_id;
        $updated = app(NativeTaskService::class)->updateFields($this->task, ['status' => $newStatus], $this->actor());
        $this->loadTask();

        if ((int) $updated->current_state_id === $previousStateId && $updated->status?->value !== $newStatus) {
            session()->flash('error', 'This task needs manager approval before it can be marked done.');

            return;
        }

        session()->flash('success', 'Status updated');
    }

    public function moveToWorkflowState(int|string $stateId): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        $stateId = (int) $stateId;
        $moved = app(NativeTaskService::class)->moveToState($this->task, $stateId, $this->actor());
        $this->loadTask();

        if ((int) $moved->current_state_id !== $stateId) {
            session()->flash('error', 'This task needs manager approval before it can be marked done.');

            return;
        }

        session()->flash('success', 'Status updated');
    }

    public function updatePriority(string $newPriority): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, ['priority' => $newPriority], $this->actor());
        $this->loadTask();
        session()->flash('success', 'Priority updated');
    }

    public function toggleAssignee(int $employeeId): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        if (in_array($employeeId, $this->assignedEmployeeIds)) {
            $this->assignedEmployeeIds = array_values(array_diff($this->assignedEmployeeIds, [$employeeId]));
        } else {
            $this->assignedEmployeeIds[] = $employeeId;
        }
        $this->saveAssignees();
    }

    public function saveAssignees(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateAssignees($this->task, $this->assignedEmployeeIds, $this->actor());
        $this->loadTask();
        session()->flash('success', 'Assignees updated');
    }

    public function toggleTag(int $tagId): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        if (in_array($tagId, $this->selectedTagIds, true)) {
            $this->selectedTagIds = array_values(array_diff($this->selectedTagIds, [$tagId]));
        } else {
            $this->selectedTagIds[] = $tagId;
        }

        $this->saveTags();
    }

    public function removeTag(int $tagId): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        $this->selectedTagIds = array_values(array_filter(
            $this->selectedTagIds,
            fn ($id) => (int) $id !== $tagId
        ));
        $this->saveTags();
    }

    public function createAndAttachTag(?string $name = null): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        $name = trim($name ?? $this->newTagName);
        if ($name === '') {
            return;
        }

        $workspaceId = $this->task->workspace_id ?? $this->actor()?->workspace_id;
        $tag = Tag::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'name' => $name,
            ],
            ['color' => $this->newTagColor ?: '#6B7280']
        );

        if (! in_array($tag->id, $this->selectedTagIds, true)) {
            $this->selectedTagIds[] = (int) $tag->id;
        }

        $this->newTagName = '';
        $this->saveTags();
    }

    public function saveTags(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }

        app(NativeTaskService::class)->updateTags($this->task, $this->selectedTagIds, $this->actor());
        $this->loadTask();
        session()->flash('success', 'Tags updated');
    }

    public function updateProject(?int $projectId): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, ['project_id' => $projectId ?: null], $this->actor());
        $this->loadTask();
        session()->flash('success', 'Project updated');
    }

    public function updatedProjectId($value): void
    {
        $normalized = $value !== null && $value !== '' ? (int) $value : null;

        if ($this->task && (int) ($this->task->project_id ?? 0) !== (int) ($normalized ?? 0)) {
            $this->updateProject($normalized);
        }
    }

    public function updateDueDate(?string $date): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, ['due_date' => $date ?: null], $this->actor());
        $this->loadTask();
        session()->flash('success', 'Due date updated');
    }

    public function updateHours(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->updateFields($this->task, [
            'estimated_hours' => $this->estimatedHours,
            'actual_hours' => $this->actualHours,
        ], $this->actor());
        $this->loadTask();
        session()->flash('success', 'Hours updated');
    }

    public function approveTask(): void
    {
        if (! $this->task) {
            return;
        }

        $actor = $this->actor();
        if (! $actor) {
            return;
        }

        $approved = app(NativeTaskService::class)->approveTask($this->task, $actor, $this->approvalNote ?: null);
        $this->approvalNote = '';
        $this->loadTask();
        if ($approved) {
            session()->flash('success', 'Task approved and marked done');
        } else {
            session()->flash('error', 'Only a manager or assigned reviewer can approve this task.');
        }
    }

    public function requestChanges(): void
    {
        if (! $this->task) {
            return;
        }

        $actor = $this->actor();
        if (! $actor) {
            return;
        }

        $requested = app(NativeTaskService::class)->requestChanges($this->task, $actor, $this->approvalNote ?: null);
        $this->approvalNote = '';
        $this->loadTask();
        if ($requested) {
            session()->flash('success', 'Changes requested. The task was sent back to In Progress.');
        } else {
            session()->flash('error', 'Only a manager or assigned reviewer can request changes.');
        }
    }

    public function addChecklist(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task || empty(trim($this->newChecklistName))) {
            return;
        }
        app(NativeTaskService::class)->addChecklist($this->task, trim($this->newChecklistName));
        $this->newChecklistName = '';
        $this->loadTask();
        session()->flash('success', 'Checklist added');
    }

    public function addChecklistItem(int $checklistId, string $itemTitle): void
    {
        $this->authorizeTaskUpdate();
        if (empty(trim($itemTitle))) {
            return;
        }
        $checklist = TaskChecklist::find($checklistId);
        if ($checklist) {
            app(NativeTaskService::class)->addChecklistItem($checklist, $itemTitle);
            $this->loadTask();
        }
    }

    public function toggleChecklistItem(int $itemId): void
    {
        $this->authorizeTaskUpdate();
        $item = TaskChecklistItem::find($itemId);
        if ($item) {
            app(NativeTaskService::class)->toggleChecklistItem($item, ! $item->is_completed, $this->actor());
            $this->loadTask();
        }
    }

    public function addSubtask(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task || empty(trim($this->newSubtaskTitle))) {
            return;
        }

        app(NativeTaskService::class)->createSubtask($this->task, [
            'title' => trim($this->newSubtaskTitle),
            'current_state_id' => $this->task->current_state_id,
            'priority' => $this->task->priority?->value,
        ], $this->actor());

        $this->newSubtaskTitle = '';
        $this->loadTask();
        session()->flash('success', 'Subtask created');
    }

    public function addComment(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task || empty(trim($this->newCommentText))) {
            return;
        }

        app(NativeTaskService::class)->addComment($this->task, $this->newCommentText, $this->actor());

        $this->newCommentText = '';
        $this->loadTask();
        session()->flash('success', 'Comment added');
    }

    public function getMentionableEmployees(): array
    {
        if (! $this->task) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $this->task->accessibleEmployeeIds())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($employee) => ['id' => (int) $employee->id, 'name' => $employee->name])
            ->values()
            ->toArray();
    }

    public function uploadAttachment(): void
    {
        $this->authorizeTaskUpdate();
        if (! $this->task || ! $this->newAttachmentFile) {
            return;
        }

        // Mirrors the validation Modules\Attachments\Controllers\
        // AttachmentController enforces on the API upload path (MIME
        // allow-list + 50MB max), which this Livewire path previously
        // skipped entirely -- it just trusted getMimeType() to label the
        // file and stored anything the browser sent.
        $this->validate([
            'newAttachmentFile' => ['required', 'file', 'max:51200', 'mimes:'.AttachmentService::ALLOWED_EXTENSIONS],
        ]);

        app(AttachmentService::class)->store(
            file: $this->newAttachmentFile,
            attachableType: Task::class,
            attachableId: $this->task->id,
            uploadedBy: $this->actor()?->id,
            disk: 'public',
        );

        $this->newAttachmentFile = null;
        $this->loadTask();
        session()->flash('success', 'Attachment uploaded');
    }

    public function deleteTask()
    {
        $this->authorizePermission('tasks.delete');
        if (! $this->task) {
            return;
        }
        app(NativeTaskService::class)->deleteTask($this->task, $this->actor());

        return redirect()->to(TaskNav::dashboardUrl());
    }

    public function render()
    {
        if (! $this->task && $this->record) {
            $this->loadTask();
        }

        $allProjects = Project::pluck('name', 'id')->toArray();
        $allEmployees = Employee::query()->with('user')->orderBy('name')->get();
        $allTags = Tag::query()->orderBy('name')->get();
        $mentionableEmployees = $this->getMentionableEmployees();
        $workflowStates = TaskQuery::defaultWorkflowStates();

        return view('livewire.task-detail.index', [
            'task' => $this->task,
            'allProjects' => $allProjects,
            'allEmployees' => $allEmployees,
            'allTags' => $allTags,
            'mentionableEmployees' => $mentionableEmployees,
            'workflowStates' => $workflowStates,
            'isManager' => $this->isManager(),
            'messageSource' => $this->task ? $this->messageSourceContext() : null,
        ]);
    }
}
