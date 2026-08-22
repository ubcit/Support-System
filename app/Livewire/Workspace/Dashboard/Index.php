<?php

namespace App\Livewire\Workspace\Dashboard;

use App\Helpers\AppShell;
use Livewire\Component;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;

class Index extends Component
{
    public string $activeTab = 'waiting';

    public ?int $selectedTaskId = null;

    public string $newNote = '';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selectedTaskId = null;
        $this->newNote = '';
    }

    public function selectTask(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->newNote = '';
    }

    public function postNote(): void
    {
        if (! $this->selectedTaskId || trim($this->newNote) === '') {
            return;
        }

        $task = Task::find($this->selectedTaskId);
        $employee = $this->resolveEmployee();
        if (! $task || ! $employee) {
            session()->flash('error', 'No employee profile linked to your account.');

            return;
        }

        app(NativeTaskService::class)->addComment($task, $this->newNote, $employee);
        $this->newNote = '';
        session()->flash('success', 'Note added');
        AppShell::refresh();
    }

    public function updateTaskStatus(int $taskId, string $newStatus): void
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

        session()->flash('success', 'Task status updated to '.strtoupper($newStatus));
        AppShell::refresh();
    }

    protected function resolveEmployee(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }

    public function render()
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return view('livewire.workspace.dashboard.index', [
                'tasks' => collect(),
                'activeTask' => null,
                'stats' => ['waiting' => 0, 'active' => 0, 'review' => 0, 'completed' => 0],
            ]);
        }

        $allTasks = Task::with(['project', 'issue', 'comments.employee.user', 'currentState', 'reviewers.employee'])
            ->whereHas('assignees', fn ($q) => $q->where('employees.id', $employee->id))
            ->get();

        $stats = [
            'waiting' => $allTasks->filter(fn ($t) => in_array($t->status->value, ['todo', 'to_do']))->count(),
            'active' => $allTasks->filter(fn ($t) => $t->status->value === 'in_progress')->count(),
            'review' => $allTasks->filter(fn ($t) => in_array($t->status->value, ['review', 'code_review']))->count(),
            'completed' => $allTasks->filter(fn ($t) => $t->status->value === 'done')->count(),
        ];

        $statusMap = [
            'waiting' => ['todo', 'to_do'],
            'active' => ['in_progress'],
            'review' => ['review', 'code_review'],
            'completed' => ['done'],
        ];

        $allowedStatuses = $statusMap[$this->activeTab] ?? ['todo', 'to_do'];
        $filteredTasks = $allTasks->filter(fn ($t) => in_array($t->status->value, $allowedStatuses));

        $activeTask = null;
        if ($this->selectedTaskId) {
            $activeTask = $allTasks->firstWhere('id', $this->selectedTaskId);
        } else {
            $activeTask = $filteredTasks->first();
        }

        return view('livewire.workspace.dashboard.index', [
            'tasks' => $filteredTasks,
            'activeTask' => $activeTask,
            'stats' => $stats,
        ]);
    }
}
