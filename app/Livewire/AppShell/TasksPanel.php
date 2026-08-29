<?php

namespace App\Livewire\AppShell;

use App\Helpers\TaskSidebarCounts;
use App\Livewire\Concerns\AuthorizesActions;
use Livewire\Component;

class TasksPanel extends Component
{
    use AuthorizesActions;
    use ListensForAppShellUpdates;

    public bool $isWorkspace = false;

    public bool $onTaskDashboard = false;

    public string $currentView = 'list';

    public ?string $currentDue = null;

    public ?string $currentProject = null;

    public string $currentScope = 'all';

    public bool $currentCompleted = false;

    public bool $currentTrashed = false;

    public ?string $currentPriority = null;

    public ?string $currentAssignee = null;

    public ?string $currentQueue = null;

    public function mount(): void
    {
        $this->isWorkspace = request()->is('workspace*');
        $this->onTaskDashboard = request()->routeIs('task-dashboard', 'workspace.employee');

        $view = request('view', 'list');
        $this->currentView = in_array($view, ['list', 'board', 'table', 'calendar', 'timeline'], true) ? $view : 'list';

        $due = request('due');
        $this->currentDue = in_array($due, ['today', 'overdue', 'week', 'upcoming'], true) ? $due : null;
        $this->currentProject = request()->filled('project') ? (string) request('project') : null;

        $scope = request('scope', 'all');
        $this->currentScope = in_array($scope, ['all', 'mine', 'created', 'unassigned'], true) ? $scope : 'all';
        // Employees default to "mine" on the dashboard when scope is omitted.
        if ($this->isWorkspace && ! request()->filled('scope') && ! $this->isManager()) {
            $this->currentScope = 'mine';
        }
        $this->currentCompleted = request()->boolean('completed');
        $this->currentTrashed = request()->boolean('trashed') && $this->isManager();

        $priority = request('priority');
        $this->currentPriority = in_array($priority, ['urgent', 'high', 'medium', 'low'], true) ? $priority : null;
        $this->currentAssignee = request()->filled('assignee') ? (string) request('assignee') : null;

        $queue = request('queue');
        $this->currentQueue = in_array($queue, ['review', 'done_recent'], true) ? $queue : null;
    }

    public function render()
    {
        $actor = auth()->user()?->resolveEmployee();
        $counts = TaskSidebarCounts::for($actor, $this->isWorkspace);

        return view('livewire.app-shell.tasks-panel', [
            'counts' => $counts,
            'isManager' => $this->isManager(),
            'canDelete' => auth()->user()?->hasPermission('tasks.delete') ?? false,
            // Trash is manager-only (employees must not see or permanently delete).
            'canManageTrash' => $this->isManager(),
        ]);
    }
}
