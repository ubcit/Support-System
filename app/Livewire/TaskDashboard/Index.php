<?php

namespace App\Livewire\TaskDashboard;

use App\Helpers\TaskNav;
use App\Helpers\TaskQuery;
use App\Livewire\Concerns\AuthorizesActions;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Tag;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\GanttTimelineService;
use Modules\Tasks\Services\KanbanEngineService;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Services\WorkflowManager;

class Index extends Component
{
    use AuthorizesActions;

    // View state
    public string $currentView = 'list';

    public string $groupBy = 'status';

    // Selection state
    public array $selectedTasks = [];

    // Filters
    public ?string $searchQuery = '';

    public ?string $filterProject = null;

    public ?string $filterPriority = null;

    public ?string $filterAssignee = null;

    public ?string $filterTag = null;

    public ?string $filterStatus = null;

    public ?string $filterDue = null;

    public string $scope = 'all';

    public string $filterReview = '';

    public bool $showCompleted = false;

    public bool $showTrashed = false;

    // Calendar navigation
    public int $calendarMonth = 0;

    public int $calendarYear = 0;

    // Sorting (for list/table)
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 50;

    public int $page = 1;

    public function loadMore(): void
    {
        $this->page++;
    }

    public function updated($name): void
    {
        $resetPageOn = [
            'searchQuery', 'filterProject', 'filterPriority', 'filterAssignee',
            'filterTag', 'filterStatus', 'filterDue', 'scope', 'showCompleted', 'showTrashed', 'filterReview',
            'sortBy', 'sortDirection', 'groupBy', 'currentView',
        ];

        if (in_array($name, $resetPageOn, true)) {
            $this->page = 1;
        }
    }

    public function mount(): void
    {
        $this->calendarMonth = now()->month;
        $this->calendarYear = now()->year;

        // Default employees (non-managers) to "My Tasks" scope
        if (! request()->filled('scope') && ! $this->isManager()) {
            $this->scope = 'mine';
        }

        if (request()->filled('view') && in_array(request('view'), ['list', 'board', 'table', 'calendar', 'timeline'], true)) {
            $this->currentView = request('view');
        }

        if (request()->filled('project')) {
            $this->filterProject = (string) request('project');
        }

        if (request()->filled('priority') && in_array(request('priority'), ['urgent', 'high', 'medium', 'low'], true)) {
            $this->filterPriority = request('priority');
        }

        if (request()->filled('due') && in_array(request('due'), ['today', 'overdue', 'week', 'upcoming'], true)) {
            $this->filterDue = request('due');
        }

        if (request()->filled('assignee')) {
            $this->filterAssignee = (string) request('assignee');
        }

        if (request()->filled('tag')) {
            $this->filterTag = (string) request('tag');
        }

        if (request()->filled('scope') && in_array(request('scope'), ['all', 'mine', 'created', 'unassigned'], true)) {
            $this->scope = request('scope');
        }

        if (request()->boolean('completed')) {
            $this->showCompleted = true;
        }

        if (request()->filled('status')) {
            $this->filterStatus = (string) request('status');
        }

        if (request()->filled('queue') && in_array(request('queue'), ['review', 'done_recent'], true)) {
            $this->filterReview = (string) request('queue');
        }

        if (request()->boolean('trashed')) {
            $this->showTrashed = true;
            $this->currentView = 'list';
            $this->showCompleted = false;
            $this->filterReview = '';
        }

        if (request()->boolean('create') && ! $this->showTrashed) {
            $this->openCreateModal();
        }

        if (request()->filled('task') && ! $this->showTrashed) {
            $this->openEditModal((int) request('task'));
        }
    }

    protected function queryString(): array
    {
        return [
            'currentView' => ['as' => 'view', 'except' => 'list'],
            'filterProject' => ['as' => 'project', 'except' => ''],
            'filterPriority' => ['as' => 'priority', 'except' => ''],
            'filterAssignee' => ['as' => 'assignee', 'except' => ''],
            'filterTag' => ['as' => 'tag', 'except' => ''],
            'filterDue' => ['as' => 'due', 'except' => ''],
            'filterStatus' => ['as' => 'status', 'except' => ''],
            'filterReview' => ['as' => 'queue', 'except' => ''],
            'scope' => ['as' => 'scope', 'except' => 'all'],
            'showCompleted' => ['as' => 'completed', 'except' => false],
            'showTrashed' => ['as' => 'trashed', 'except' => false],
            'editingTaskId' => ['as' => 'task', 'except' => null],
        ];
    }

    public function setView(string $view): void
    {
        if ($this->showTrashed) {
            $this->currentView = 'list';

            return;
        }

        $this->currentView = $view;
    }

    public function setGroupBy(string $group): void
    {
        $this->groupBy = $group;
    }

    public function toggleSelectTask(int $taskId): void
    {
        if (in_array($taskId, $this->selectedTasks)) {
            $this->selectedTasks = array_values(array_diff($this->selectedTasks, [$taskId]));
        } else {
            $this->selectedTasks[] = $taskId;
        }
    }

    public function selectAllTasks(array $taskIds): void
    {
        $allSelected = count(array_intersect($taskIds, $this->selectedTasks)) === count($taskIds) && count($taskIds) > 0;
        if ($allSelected) {
            $this->selectedTasks = array_values(array_diff($this->selectedTasks, $taskIds));
        } else {
            $this->selectedTasks = array_values(array_unique(array_merge($this->selectedTasks, $taskIds)));
        }
    }

    public function clearSelection(): void
    {
        $this->selectedTasks = [];
        $this->dispatch('task-selection-cleared');
    }

    protected function actor(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }

    protected function statusChangeBlocked(Task $task, int $previousStateId, string $wantedStatus): bool
    {
        return (int) $task->current_state_id === $previousStateId
            && $task->statusKey() !== $wantedStatus
            && $task->status?->value !== $wantedStatus;
    }

    public function canCreateTasks(): bool
    {
        return auth()->user()?->hasPermission('tasks.create') ?? false;
    }

    public function canDeleteTasks(): bool
    {
        return auth()->user()?->hasPermission('tasks.delete') ?? false;
    }

    public function updateTaskStatus(int $taskId, string $status): void
    {
        $task = Task::find($taskId);
        if (! $task || $task->statusKey() === $status || $task->status?->value === $status) {
            return;
        }

        $previousStateId = (int) $task->current_state_id;
        $updated = app(NativeTaskService::class)->updateFields($task, ['status' => $status], $this->actor());
        if ($this->statusChangeBlocked($updated, $previousStateId, $status)) {
            session()->flash('error', 'This task needs manager approval before it can be marked done.');

            return;
        }

        session()->flash('success', 'Status updated.');
    }

    public function approveTask(int $taskId, ?string $note = null): void
    {
        $task = Task::find($taskId);
        $actor = $this->actor();
        if (! $task || ! $actor) {
            return;
        }

        $approved = app(NativeTaskService::class)->approveTask($task, $actor, $note);
        session()->flash(
            $approved ? 'success' : 'error',
            $approved ? 'Task approved and marked done.' : 'Only a manager or assigned reviewer can approve this task.'
        );
    }

    public function openReviewModal(int $taskId): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            return;
        }

        $this->reviewingTaskId = $task->id;
        $this->reviewingTaskTitle = $task->title;
        $this->reviewNote = '';
        $this->showReviewModal = true;
    }

    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
        $this->reviewingTaskId = null;
        $this->reviewingTaskTitle = '';
        $this->reviewNote = '';
    }

    public function submitReview(): void
    {
        if (! $this->reviewingTaskId) {
            return;
        }

        $this->requestChanges($this->reviewingTaskId, $this->reviewNote ?: null);
        $this->closeReviewModal();
    }

    public function requestChanges(int $taskId, ?string $note = null): void
    {
        $task = Task::find($taskId);
        $actor = $this->actor();
        if (! $task || ! $actor) {
            return;
        }

        $requested = app(NativeTaskService::class)->requestChanges($task, $actor, $note);
        session()->flash(
            $requested ? 'success' : 'error',
            $requested ? 'Changes requested. The task was sent back to In Progress.' : 'Only a manager or assigned reviewer can request changes.'
        );
    }

    public function updateWipLimit(int $stateId, mixed $limit = 0): void
    {
        if (! $this->isManager()) {
            return;
        }

        WorkflowState::whereKey($stateId)->update([
            'wip_limit' => max(0, (int) $limit),
        ]);
        session()->flash('success', 'WIP limit updated.');
    }

    public function updateTaskPriority(int $taskId, string $priority): void
    {
        $task = Task::find($taskId);
        if (! $task || ($task->priority?->value === $priority)) {
            return;
        }

        app(NativeTaskService::class)->updateFields($task, ['priority' => $priority], $this->actor());
        session()->flash('success', 'Priority updated.');
    }

    public function updateTaskAssignee(int $taskId, ?int $employeeId): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            return;
        }

        $currentIds = $task->assignees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();
        $nextIds = $employeeId ? [(int) $employeeId] : [];
        sort($currentIds);
        sort($nextIds);
        if ($currentIds === $nextIds) {
            return;
        }

        app(NativeTaskService::class)->updateAssignees($task, $nextIds, $this->actor());
        session()->flash('success', 'Assignee updated.');
    }

    public function toggleTaskAssignee(int $taskId, int $employeeId): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            return;
        }

        $currentIds = $task->assignees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();
        if (in_array($employeeId, $currentIds, true)) {
            $nextIds = array_values(array_diff($currentIds, [$employeeId]));
        } else {
            $nextIds = array_values(array_unique([...$currentIds, $employeeId]));
        }

        app(NativeTaskService::class)->updateAssignees($task, $nextIds, $this->actor());
        session()->flash('success', 'Assignees updated.');
    }

    public function updateTaskProject(int $taskId, ?int $projectId): void
    {
        $task = Task::find($taskId);
        $projectId = $projectId ?: null;
        if (! $task || (int) ($task->project_id ?? 0) === (int) ($projectId ?? 0)) {
            return;
        }

        app(NativeTaskService::class)->updateFields($task, ['project_id' => $projectId], $this->actor());
        session()->flash('success', 'Project updated.');
    }

    public function updateTaskDueDate(int $taskId, ?string $dueDate): void
    {
        $task = Task::find($taskId);
        if ($task) {
            app(NativeTaskService::class)->updateFields($task, ['due_date' => $dueDate ?: null], $this->actor());
            session()->flash('success', 'Due date updated.');
        }
    }

    public function moveTaskToDueGroup(int $taskId, string $groupKey): void
    {
        $dueDate = match ($groupKey) {
            'overdue' => now()->subDay()->toDateString(),
            'today' => now()->toDateString(),
            'tomorrow' => now()->addDay()->toDateString(),
            'later' => now()->addWeek()->toDateString(),
            'none' => null,
            default => null,
        };

        if (! in_array($groupKey, ['overdue', 'today', 'tomorrow', 'later', 'none'], true)) {
            return;
        }

        $this->updateTaskDueDate($taskId, $dueDate);
    }

    public function bulkUpdateStatus(string $status): void
    {
        if (empty($this->selectedTasks)) {
            return;
        }

        $tasks = Task::whereIn('id', $this->selectedTasks)->get();
        $taskService = app(NativeTaskService::class);
        $updatedCount = 0;
        $blockedCount = 0;
        foreach ($tasks as $task) {
            $previousStateId = (int) $task->current_state_id;
            $updated = $taskService->updateFields($task, ['status' => $status], $this->actor());
            if ($this->statusChangeBlocked($updated, $previousStateId, $status)) {
                $blockedCount++;
            } else {
                $updatedCount++;
            }
        }
        $this->selectedTasks = [];
        if ($blockedCount > 0 && $updatedCount === 0) {
            session()->flash('error', 'Those tasks need manager approval before they can be marked done.');
            $this->dispatch('task-selection-cleared');

            return;
        }

        session()->flash(
            $blockedCount > 0 ? 'error' : 'success',
            $updatedCount.' tasks status updated.'.($blockedCount > 0 ? " {$blockedCount} still need approval." : '')
        );
        $this->dispatch('task-selection-cleared');
    }

    public function bulkUpdatePriority(string $priority): void
    {
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = count($this->selectedTasks);
        app(NativeTaskService::class)->bulkUpdatePriority($this->selectedTasks, $priority, $this->actor());
        $this->selectedTasks = [];
        session()->flash('success', $count.' tasks priority updated.');
        $this->dispatch('task-selection-cleared');
    }

    public function bulkAssign(?int $employeeId): void
    {
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = count($this->selectedTasks);
        app(NativeTaskService::class)->bulkUpdateAssignee($this->selectedTasks, $employeeId, $this->actor());
        $this->selectedTasks = [];
        session()->flash('success', $count.' tasks assignee updated.');
        $this->dispatch('task-selection-cleared');
    }

    public function bulkUpdateDueDate(?string $dueDate): void
    {
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = count($this->selectedTasks);
        app(NativeTaskService::class)->bulkUpdateDueDate($this->selectedTasks, $dueDate, $this->actor());
        $this->selectedTasks = [];
        session()->flash('success', $count.' tasks due date updated.');
        $this->dispatch('task-selection-cleared');
    }

    public function bulkDelete(): void
    {
        $this->authorizePermission('tasks.delete');
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = count($this->selectedTasks);
        app(NativeTaskService::class)->bulkDelete($this->selectedTasks, $this->actor());

        $this->selectedTasks = [];
        session()->flash('success', "{$count} tasks moved to trash.");
        $this->dispatch('task-selection-cleared');
    }

    public function bulkRestore(): void
    {
        $this->authorizePermission('tasks.delete');
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = count($this->selectedTasks);
        app(NativeTaskService::class)->bulkRestore($this->selectedTasks, $this->actor());

        $this->selectedTasks = [];
        session()->flash('success', "{$count} tasks restored.");
        $this->dispatch('task-selection-cleared');
    }

    public function bulkForceDelete(): void
    {
        $this->authorizePermission('tasks.delete');
        if (empty($this->selectedTasks)) {
            return;
        }

        $count = 0;
        $service = app(NativeTaskService::class);
        foreach (Task::onlyTrashed()->whereIn('id', $this->selectedTasks)->get() as $task) {
            $service->forceDeleteTask($task, $this->actor());
            $count++;
        }

        $this->selectedTasks = [];
        session()->flash('success', "{$count} tasks permanently deleted.");
        $this->dispatch('task-selection-cleared');
    }

    // Create / Edit task modals
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showReviewModal = false;

    public ?int $reviewingTaskId = null;

    public string $reviewingTaskTitle = '';

    public string $reviewNote = '';

    public ?int $editingTaskId = null;

    public string $formTitle = '';

    public mixed $formProjectId = null;

    public string $formPriority = 'medium';

    public string $formStatus = 'to_do';

    public mixed $formAssigneeId = null;

    public array $formAssigneeIds = [];

    public array $formTagIds = [];

    public string $formNewTagName = '';

    public string $formNewTagColor = '#6B7280';

    public ?string $formDueDate = null;

    public ?string $formStartDate = null;

    public string $formDescription = '';

    public bool $editingTaskMustPassReview = false;

    public function openCreateModal(?string $prefillDueDate = null): void
    {
        $this->resetTaskForm();
        $this->formPriority = 'medium';
        $this->formStatus = 'to_do';
        if ($this->filterProject) {
            $this->formProjectId = (int) $this->filterProject;
        }
        if ($this->scope === 'mine' && $this->actor()) {
            $this->formAssigneeIds = [(string) $this->actor()->id];
        }
        if ($prefillDueDate) {
            $this->formDueDate = $prefillDueDate;
        }
        $this->showCreateModal = true;
    }

    public function openEditModal(int $taskId): void
    {
        $this->showEditModal = true;
        $this->editingTaskId = $taskId;

        $task = Task::with(['assignees', 'reviewers', 'tags'])->find($taskId);
        if (! $task) {
            $this->closeEditModal();

            return;
        }

        $this->editingTaskId = $task->id;
        $this->formTitle = $task->title;
        $this->formProjectId = $task->project_id;
        $this->formPriority = $task->priority?->value ?? 'medium';
        $this->formStatus = $task->statusKey();
        $this->formAssigneeIds = $task->assignees->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->formTagIds = $task->tags->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->formDueDate = $task->due_date?->format('Y-m-d');
        $this->formStartDate = $task->start_date?->format('Y-m-d');
        $this->formDescription = (string) $task->description;
        $this->editingTaskMustPassReview = $task->mustPassReview();
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetTaskForm();
    }

    public function createTask(): ?int
    {
        $this->authorizePermission('tasks.create');
        $this->validate([
            'formTitle' => ['required', 'string', 'max:255'],
            'formProjectId' => ['nullable', 'integer'],
            'formPriority' => ['required', 'in:low,medium,high,urgent'],
            'formAssigneeIds' => ['array'],
            'formDueDate' => ['nullable', 'date'],
            'formStartDate' => ['nullable', 'date'],
            'formDescription' => ['nullable', 'string'],
        ]);

        $workflowManager = app(WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('task');
        $actor = $this->actor();

        $assigneeIds = collect($this->formAssigneeIds)->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($assigneeIds === [] && $this->scope === 'mine' && $actor) {
            $assigneeIds = [$actor->id];
        }

        $task = app(NativeTaskService::class)->createTask([
            'title' => $this->formTitle,
            'project_id' => $this->formProjectId,
            'priority' => $this->formPriority,
            'due_date' => $this->formDueDate,
            'start_date' => $this->formStartDate,
            'description' => $this->formDescription ?: null,
            'type' => 'task',
            'workflow_id' => $initialState?->workflow_id,
            'current_state_id' => $initialState?->id,
            'created_by' => $actor?->id,
            'assignee_ids' => $assigneeIds,
            'tag_ids' => collect($this->formTagIds)->filter()->map(fn ($id) => (int) $id)->values()->all(),
        ], $actor);

        $this->showCreateModal = false;
        $this->resetTaskForm();
        session()->flash('success', 'Task created successfully');

        return $task->id;
    }

    public function updateTask(): void
    {
        $this->authorizePermission('tasks.edit');
        $this->validate([
            'formTitle' => ['required', 'string', 'max:255'],
            'formProjectId' => ['nullable', 'integer'],
            'formPriority' => ['required', 'in:low,medium,high,urgent'],
            'formStatus' => ['required', 'string'],
            'formAssigneeIds' => ['array'],
            'formDueDate' => ['nullable', 'date'],
            'formStartDate' => ['nullable', 'date'],
            'formDescription' => ['nullable', 'string'],
        ]);

        $task = Task::find($this->editingTaskId);
        if (! $task) {
            return;
        }

        $actor = $this->actor();
        $taskService = app(NativeTaskService::class);

        $taskService->updateFields($task, [
            'title' => $this->formTitle,
            'project_id' => $this->formProjectId,
            'priority' => $this->formPriority,
            'status' => $this->formStatus,
            'due_date' => $this->formDueDate,
            'start_date' => $this->formStartDate,
            'description' => $this->formDescription ?: null,
        ], $actor);

        $taskService->updateAssignees($task, $this->formAssigneeIds, $actor);
        $taskService->updateTags(
            $task,
            collect($this->formTagIds)->filter()->map(fn ($id) => (int) $id)->values()->all(),
            $actor
        );

        $this->closeEditModal();
        session()->flash('success', 'Task Updated');
    }

    public function createFormTag(?string $name = null): void
    {
        $name = trim($name ?? $this->formNewTagName);
        if ($name === '') {
            return;
        }

        $workspaceId = $this->actor()?->workspace_id;
        $tag = Tag::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'name' => $name,
            ],
            ['color' => $this->formNewTagColor ?: '#6B7280']
        );

        $id = (string) $tag->id;
        if (! in_array($id, $this->formTagIds, true)) {
            $this->formTagIds[] = $id;
        }

        $this->formNewTagName = '';
    }

    public function toggleTaskTag(int $taskId, int $tagId): void
    {
        $this->authorizePermission('tasks.edit');
        $task = Task::with('tags')->find($taskId);
        if (! $task) {
            return;
        }

        $ids = $task->tags->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (in_array($tagId, $ids, true)) {
            $ids = array_values(array_diff($ids, [$tagId]));
        } else {
            $ids[] = $tagId;
        }

        app(NativeTaskService::class)->updateTags($task, $ids, $this->actor());
    }

    public function quickCreateTaskTag(int $taskId, string $name, ?string $color = null): void
    {
        $this->authorizePermission('tasks.edit');
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $task = Task::with('tags')->find($taskId);
        if (! $task) {
            return;
        }

        $workspaceId = $task->workspace_id ?? $this->actor()?->workspace_id;
        $tag = Tag::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'name' => $name,
            ],
            ['color' => $color ?: $this->formNewTagColor ?: '#6B7280']
        );

        $ids = $task->tags->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) $tag->id, $ids, true)) {
            $ids[] = (int) $tag->id;
        }

        app(NativeTaskService::class)->updateTags($task, $ids, $this->actor());
    }

    protected function resetTaskForm(): void
    {
        $this->editingTaskId = null;
        $this->formTitle = '';
        $this->formProjectId = null;
        $this->formPriority = 'medium';
        $this->formStatus = 'to_do';
        $this->formAssigneeId = null;
        $this->formAssigneeIds = [];
        $this->formTagIds = [];
        $this->formNewTagName = '';
        $this->formNewTagColor = '#6B7280';
        $this->formDueDate = null;
        $this->formStartDate = null;
        $this->formDescription = '';
        $this->editingTaskMustPassReview = false;
    }

    public function deleteTask(int $taskId): void
    {
        $this->authorizePermission('tasks.delete');
        $task = Task::find($taskId);
        if ($task) {
            app(NativeTaskService::class)->deleteTask($task, $this->actor());
            session()->flash('success', 'Task moved to trash.');
        }
    }

    public function restoreTask(int $taskId): void
    {
        $this->authorizePermission('tasks.delete');
        $task = Task::onlyTrashed()->find($taskId);
        if ($task) {
            app(NativeTaskService::class)->restoreTask($task, $this->actor());
            session()->flash('success', 'Task restored.');
        }
    }

    public function forceDeleteTask(int $taskId): void
    {
        $this->authorizePermission('tasks.delete');
        $task = Task::onlyTrashed()->find($taskId);
        if ($task) {
            app(NativeTaskService::class)->forceDeleteTask($task, $this->actor());
            session()->flash('success', 'Task permanently deleted.');
        }
    }

    public function setSortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function prevMonth(): void
    {
        if ($this->calendarMonth === 1) {
            $this->calendarMonth = 12;
            $this->calendarYear--;
        } else {
            $this->calendarMonth--;
        }
    }

    public function nextMonth(): void
    {
        if ($this->calendarMonth === 12) {
            $this->calendarMonth = 1;
            $this->calendarYear++;
        } else {
            $this->calendarMonth++;
        }
    }

    public function moveTaskToState(int $taskId, int $stateId): void
    {
        $task = Task::find($taskId);
        if (! $task || (int) $task->current_state_id === $stateId) {
            return;
        }

        $moved = app(NativeTaskService::class)->moveToState($task, $stateId, $this->actor());
        if ($moved->current_state_id === $stateId) {
            session()->flash('success', "Moved to {$moved->currentState->name}.");
        } else {
            session()->flash('error', 'This task needs manager approval before it can be marked done.');
        }
    }

    public function quickCreateTask(
        string $title,
        ?int $stateId = null,
        ?int $projectId = null,
        ?string $priority = null,
        ?int $assigneeId = null,
    ): ?int {
        if (! $this->canCreateTasks()) {
            return null;
        }

        if (empty(trim($title))) {
            return null;
        }

        $actor = $this->actor();
        $extra = [];

        if ($projectId) {
            $extra['project_id'] = $projectId;
        } elseif ($this->filterProject) {
            $extra['project_id'] = (int) $this->filterProject;
        }

        if ($priority && in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $extra['priority'] = $priority;
        }

        if ($assigneeId) {
            $extra['assignee_ids'] = [$assigneeId];
        } elseif ($this->scope === 'mine' && $actor) {
            $extra['assignee_ids'] = [$actor->id];
        }

        $task = app(NativeTaskService::class)->quickCreate($title, $stateId, $actor, $extra);
        session()->flash('success', 'Task created.');

        return $task->id;
    }

    public function quickCreateSubtask(int $parentId, string $title): ?int
    {
        if (! $this->canCreateTasks()) {
            return null;
        }

        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $parent = Task::query()->whereNull('parent_id')->find($parentId);
        if (! $parent) {
            return null;
        }

        $task = app(NativeTaskService::class)->createSubtask($parent, [
            'title' => $title,
        ], $this->actor());

        session()->flash('success', 'Subtask created.');

        return $task->id;
    }

    public function editTask(): void
    {
        $this->updateTask();
    }

    public function clearFilter(string $key): void
    {
        match ($key) {
            'project' => $this->filterProject = null,
            'scope' => $this->scope = 'all',
            'due' => $this->filterDue = null,
            'priority' => $this->filterPriority = null,
            'assignee' => $this->filterAssignee = null,
            'tag' => $this->filterTag = null,
            'status' => $this->filterStatus = null,
            'completed' => $this->showCompleted = false,
            'trashed' => $this->showTrashed = false,
            'review' => $this->filterReview = '',
            'search' => $this->searchQuery = '',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardFilters(): array
    {
        return [
            'search' => $this->searchQuery,
            'project_id' => $this->filterProject ? (int) $this->filterProject : null,
            'priority' => $this->filterPriority,
            'assignee_id' => $this->filterAssignee ? (int) $this->filterAssignee : null,
            'tag_id' => $this->filterTag ? (int) $this->filterTag : null,
            'due' => $this->filterDue,
            'scope' => $this->scope,
            'status' => $this->filterStatus ?: ($this->filterReview === 'review' ? 'review' : null),
            'show_completed' => $this->showCompleted || $this->filterReview === 'done_recent',
            'completed_only' => $this->showCompleted || $this->filterReview === 'done_recent',
            'trashed' => $this->showTrashed,
        ];
    }

    public function render()
    {
        $actor = $this->actor();
        $filters = $this->dashboardFilters();

        $query = TaskQuery::dashboardQuery($filters, $actor)
            ->withCount(['attachments', 'comments'])
            ->with([
                'assignees.user',
                'project',
                'tags',
                'checklists.items',
                'currentState',
                'reviewers',
                'directSubtasks' => fn ($q) => $q
                    ->whereNull('archived_at')
                    ->with(['currentState', 'assignees.user', 'tags'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'activityLogs' => fn ($q) => $q->where(function ($q) {
                    $q->whereIn('action', ['state_moved', 'bulk_status_changed'])
                        ->orWhere(function ($q) {
                            $q->where('action', 'field_updated')
                                ->whereIn('field', ['status', 'current_state_id']);
                        });
                })->orderBy('created_at'),
            ]);

        $allowedSorts = ['id', 'title', 'priority', 'due_date', 'estimated_hours', 'created_at'];
        $sortBy = in_array($this->sortBy, $allowedSorts, true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $limit = $this->perPage * $this->page;
        $orderedQuery = $query->orderBy($sortBy, $sortDirection);
        if ($this->filterReview === 'done_recent') {
            $orderedQuery->orderByDesc('completed_at');
        }
        $tasks = $orderedQuery->take($limit + 1)->get();
        $hasMoreTasks = $tasks->count() > $limit;
        $tasks = $tasks->take($limit)->values();

        $projects = Project::query()->orderBy('name')->pluck('name', 'id')->toArray();
        $employeeRoster = Employee::query()->with('user')->orderBy('name')->get();
        $employees = $employeeRoster->pluck('name', 'id')->toArray();
        $workflowStates = TaskQuery::defaultWorkflowStates();

        $groupedTasks = $this->buildGroupedTasks($tasks, $projects, $employees, $workflowStates);

        $board = ['columns' => []];
        $timeline = ['tasks' => []];
        $view = $this->showTrashed ? 'list' : $this->currentView;
        if ($view === 'board') {
            $board = app(KanbanEngineService::class)->getBoardData(
                $filters['project_id'],
                null,
                array_merge($filters, ['actor' => $actor]),
            );
        }
        if ($view === 'timeline') {
            $timeline = app(GanttTimelineService::class)->getTimelineData($filters['project_id'], $filters, $actor);
        }

        $selectedProjectName = ($this->filterProject && isset($projects[(int) $this->filterProject]))
            ? $projects[(int) $this->filterProject]
            : null;

        TaskNav::remember([
            'view' => $this->showTrashed ? 'list' : $this->currentView,
            'project' => $this->filterProject,
            'due' => $this->filterDue,
            'scope' => $this->scope,
            'completed' => $this->showCompleted ? 1 : null,
            'trashed' => $this->showTrashed ? 1 : null,
            'priority' => $this->filterPriority,
            'assignee' => $this->filterAssignee,
            'tag' => $this->filterTag,
            'status' => $this->filterStatus,
        ]);

        $tagModels = Tag::query()->orderBy('name')->get();
        $tagOptions = $tagModels->pluck('name', 'id')->toArray();

        return view('livewire.task-dashboard.index', [
            'tasks' => $tasks,
            'groupedTasks' => $groupedTasks,
            'board' => $board,
            'timeline' => $timeline,
            'projects' => $projects,
            'employees' => $employees,
            'employeeRoster' => $employeeRoster,
            'workflowStates' => $workflowStates,
            'tagOptions' => $tagOptions,
            'tagModels' => $tagModels,
            'selectedProjectName' => $selectedProjectName,
            'filterSubtitle' => $this->filterSubtitle($selectedProjectName),
            'activeFilters' => $this->activeFilterChips($selectedProjectName, $employees, $tagOptions),
            'isManager' => $this->isManager(),
            'canCreate' => $this->canCreateTasks() && ! $this->showTrashed,
            'canDelete' => $this->canDeleteTasks(),
            'hasMoreTasks' => $hasMoreTasks,
            'showTrashed' => $this->showTrashed,
        ]);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  array<int, string>  $projects
     * @param  array<int, string>  $employees
     * @param  Collection<int, mixed>  $workflowStates
     * @return Collection<string|int, array<string, mixed>>
     */
    protected function buildGroupedTasks($tasks, array $projects, array $employees, $workflowStates)
    {
        $groupedTasks = collect();

        if ($this->groupBy === 'priority') {
            $priorities = ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
            foreach ($priorities as $priKey => $priName) {
                $groupTasks = $tasks->filter(fn ($t) => ($t->priority?->value ?? 'medium') === $priKey)->values();
                if ($groupTasks->isEmpty()) {
                    continue;
                }
                $groupedTasks->put($priKey, [
                    'title' => $priName,
                    'type' => 'priority',
                    'color' => match ($priKey) {
                        'urgent' => 'red',
                        'high' => 'amber',
                        'medium' => 'blue',
                        default => 'gray',
                    },
                    'tasks' => $groupTasks,
                ]);
            }
        } elseif ($this->groupBy === 'due_date') {
            $buckets = [
                'overdue' => ['title' => 'Overdue', 'color' => 'red'],
                'today' => ['title' => 'Today', 'color' => 'yellow'],
                'tomorrow' => ['title' => 'Tomorrow', 'color' => 'cyan'],
                'later' => ['title' => 'Later', 'color' => 'blue'],
                'none' => ['title' => 'No due date', 'color' => 'gray'],
            ];

            foreach ($buckets as $key => $meta) {
                $groupTasks = $tasks->filter(function (Task $task) use ($key) {
                    $urgency = Task::urgencyForDueDate($task->due_date, false);

                    return match ($key) {
                        'overdue' => $urgency === 'overdue',
                        'today' => $urgency === 'today',
                        'tomorrow' => $urgency === 'tomorrow',
                        'later' => $task->due_date && $urgency === null,
                        'none' => ! $task->due_date,
                        default => false,
                    };
                })->sortBy(fn (Task $task) => $task->due_date?->timestamp ?? PHP_INT_MAX)->values();

                if ($groupTasks->isEmpty()) {
                    continue;
                }

                $groupedTasks->put($key, [
                    'title' => $meta['title'],
                    'type' => 'due_date',
                    'color' => $meta['color'],
                    'tasks' => $groupTasks,
                ]);
            }
        } elseif ($this->groupBy === 'project') {
            foreach ($projects as $pid => $pname) {
                $groupTasks = $tasks->filter(fn ($t) => $t->project_id == $pid)->values();
                if ($groupTasks->isEmpty()) {
                    continue;
                }
                $groupedTasks->put($pid, [
                    'title' => $pname,
                    'type' => 'project',
                    'color' => 'indigo',
                    'tasks' => $groupTasks,
                ]);
            }
            $noProj = $tasks->filter(fn ($t) => empty($t->project_id))->values();
            if ($noProj->isNotEmpty()) {
                $groupedTasks->put('none', [
                    'title' => 'No Project',
                    'type' => 'project',
                    'color' => 'gray',
                    'tasks' => $noProj,
                ]);
            }
        } elseif ($this->groupBy === 'assignee') {
            foreach ($employees as $eid => $ename) {
                $groupTasks = $tasks->filter(fn ($t) => $t->assignees->pluck('id')->contains($eid))->values();
                if ($groupTasks->isEmpty()) {
                    continue;
                }
                $groupedTasks->put($eid, [
                    'title' => $ename,
                    'type' => 'assignee',
                    'color' => 'purple',
                    'tasks' => $groupTasks,
                ]);
            }
            $unassigned = $tasks->filter(fn ($t) => $t->assignees->isEmpty())->values();
            if ($unassigned->isNotEmpty()) {
                $groupedTasks->put('unassigned', [
                    'title' => 'Unassigned',
                    'type' => 'assignee',
                    'color' => 'gray',
                    'tasks' => $unassigned,
                ]);
            }
        } else {
            $firstStateId = $workflowStates->first()?->id;
            foreach ($workflowStates as $state) {
                $groupTasks = $tasks->filter(function ($t) use ($state, $firstStateId) {
                    if ($t->current_state_id === $state->id) {
                        return true;
                    }

                    return $state->id === $firstStateId && ! $t->current_state_id;
                })->values();
                if ($groupTasks->isEmpty()) {
                    continue;
                }
                $groupedTasks->put($state->id, [
                    'title' => $state->name,
                    'type' => 'status',
                    'state_id' => $state->id,
                    'state_type' => $state->type,
                    'color' => str_contains(strtolower((string) $state->name), 'review')
                        ? 'purple'
                        : match ($state->type) {
                            'initial' => 'gray',
                            'active' => 'blue',
                            'completed' => 'emerald',
                            default => 'gray',
                        },
                    'tasks' => $groupTasks,
                ]);
            }
        }

        return $groupedTasks;
    }

    protected function filterSubtitle(?string $selectedProjectName): string
    {
        $parts = [$selectedProjectName ?: 'All projects'];

        if ($this->scope === 'mine') {
            $parts[] = 'Assigned to you';
        } elseif ($this->scope === 'created') {
            $parts[] = 'Created by you';
        } elseif ($this->scope === 'unassigned') {
            $parts[] = 'Unassigned';
        }

        if ($this->filterDue === 'today') {
            $parts[] = 'Due today';
        } elseif ($this->filterDue === 'overdue') {
            $parts[] = 'Overdue';
        } elseif ($this->filterDue === 'week') {
            $parts[] = 'This week';
        } elseif ($this->filterDue === 'upcoming') {
            $parts[] = 'Upcoming';
        }

        if ($this->showCompleted) {
            $parts[] = 'Completed';
        }

        if ($this->showTrashed) {
            $parts[] = 'Trash';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<int, string>  $employees
     * @param  array<int, string>  $tagOptions
     * @return list<array{key: string, label: string}>
     */
    protected function activeFilterChips(?string $selectedProjectName, array $employees, array $tagOptions = []): array
    {
        $chips = [];

        if ($selectedProjectName) {
            $chips[] = ['key' => 'project', 'label' => $selectedProjectName];
        }
        if ($this->scope === 'mine') {
            $chips[] = ['key' => 'scope', 'label' => 'Assigned to you'];
        } elseif ($this->scope === 'created') {
            $chips[] = ['key' => 'scope', 'label' => 'Created by you'];
        } elseif ($this->scope === 'unassigned') {
            $chips[] = ['key' => 'scope', 'label' => 'Unassigned'];
        }
        if ($this->filterDue === 'today') {
            $chips[] = ['key' => 'due', 'label' => 'Due today'];
        } elseif ($this->filterDue === 'overdue') {
            $chips[] = ['key' => 'due', 'label' => 'Overdue'];
        } elseif ($this->filterDue === 'week') {
            $chips[] = ['key' => 'due', 'label' => 'This week'];
        } elseif ($this->filterDue === 'upcoming') {
            $chips[] = ['key' => 'due', 'label' => 'Upcoming'];
        }
        if ($this->filterPriority) {
            $chips[] = ['key' => 'priority', 'label' => ucfirst($this->filterPriority).' priority'];
        }
        if ($this->filterAssignee && isset($employees[(int) $this->filterAssignee])) {
            $chips[] = ['key' => 'assignee', 'label' => $employees[(int) $this->filterAssignee]];
        }
        if ($this->filterTag && isset($tagOptions[(int) $this->filterTag])) {
            $chips[] = ['key' => 'tag', 'label' => 'Tag: '.$tagOptions[(int) $this->filterTag]];
        }
        if ($this->showCompleted) {
            $chips[] = ['key' => 'completed', 'label' => 'Completed'];
        }
        if ($this->showTrashed) {
            $chips[] = ['key' => 'trashed', 'label' => 'Trash'];
        }
        if ($this->filterReview === 'review') {
            $chips[] = ['key' => 'review', 'label' => 'Review Queue'];
        } elseif ($this->filterReview === 'done_recent') {
            $chips[] = ['key' => 'review', 'label' => 'Recently Completed'];
        }

        return $chips;
    }
}
