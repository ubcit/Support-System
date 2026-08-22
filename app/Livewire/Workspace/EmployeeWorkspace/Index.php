<?php

namespace App\Livewire\Workspace\EmployeeWorkspace;

use App\Helpers\AppShell;
use Livewire\Component;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;

class Index extends Component
{
    public string $activeTab = 'queue';

    public ?int $selectedTaskId = null;

    public string $newComment = '';

    protected function queryString(): array
    {
        return [
            'activeTab' => ['as' => 'tab', 'except' => 'queue'],
            'selectedTaskId' => ['as' => 'task', 'except' => null],
        ];
    }

    public function mount(): void
    {
        if (request()->filled('tab') && in_array(request('tab'), ['queue', 'overdue', 'today', 'completed'], true)) {
            $this->activeTab = request('tab');
        }

        if (request()->filled('task')) {
            $this->selectedTaskId = (int) request('task');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selectedTaskId = null;
        $this->newComment = '';
    }

    public function selectTask(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newComment = '';
    }

    public function updateStatus(int $taskId, string $newStatus): void
    {
        $task = Task::find($taskId);
        $employee = $this->resolveEmployee();
        if (! $task || ! $employee) {
            return;
        }

        $previousStateId = (int) $task->current_state_id;
        $updated = app(NativeTaskService::class)->updateFields($task, ['status' => $newStatus], $employee);

        if ((int) $updated->current_state_id === $previousStateId && $updated->statusKey() !== $newStatus && $updated->status?->value !== $newStatus) {
            session()->flash('error', 'This task needs manager approval before it can be marked done.');

            return;
        }

        session()->flash('success', 'Status updated to '.ucfirst(str_replace('_', ' ', $newStatus)));
        AppShell::refresh();
    }

    public function addComment(): void
    {
        if (empty(trim($this->newComment)) || ! $this->selectedTaskId) {
            return;
        }

        $task = Task::find($this->selectedTaskId);
        $employee = $this->resolveEmployee();
        if (! $task || ! $employee) {
            return;
        }

        app(NativeTaskService::class)->addComment($task, $this->newComment, $employee);

        $this->newComment = '';
        session()->flash('success', 'Comment added');
        AppShell::refresh();
    }

    protected function resolveEmployee(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }

    public function render()
    {
        $employee = $this->resolveEmployee();

        $allTasks = $employee
            ? Task::query()
                ->visibleTo($employee)
                ->whereNull('archived_at')
                ->whereNull('parent_id')
                ->with(['project', 'checklists.items', 'assignees.user', 'currentState', 'comments.employee.user', 'activityLogs.employee', 'reviewers.employee'])
                ->get()
            : collect();

        $queue = $allTasks->whereNull('completed_at')->values();
        $overdue = $queue->filter(fn ($t) => $t->isOverdue());
        $completed = $allTasks->whereNotNull('completed_at')->values();
        $today = now()->toDateString();
        $todayTasks = $queue->filter(fn ($t) => $t->due_date && $t->due_date->toDateString() === $today);

        $tabTasks = match ($this->activeTab) {
            'overdue' => $overdue,
            'completed' => $completed,
            'today' => $todayTasks,
            default => $queue,
        };

        $selectedTask = $this->selectedTaskId
            ? $tabTasks->firstWhere('id', $this->selectedTaskId)
            : null;

        if (! $selectedTask && $tabTasks->isNotEmpty()) {
            $selectedTask = $tabTasks->first();
            $this->selectedTaskId = $selectedTask->id;
        } elseif (! $selectedTask) {
            $this->selectedTaskId = null;
        }

        return view('livewire.workspace.employee-workspace.index', [
            'employee' => $employee,
            'tab_tasks' => $tabTasks,
            'selected_task' => $selectedTask,
            'stats' => [
                'queue' => $queue->count(),
                'overdue' => $overdue->count(),
                'today' => $todayTasks->count(),
                'completed' => $completed->count(),
            ],
        ]);
    }
}
