<?php

namespace App\Livewire\BossWorkspace;

use Livewire\Component;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class Index extends Component
{
    public function render()
    {
        $employees = Employee::with('user')->withCount(['assignments as active_count' => function ($q) {
            $q->whereHas('task', fn ($t) => $t->whereNull('completed_at'));
        }])->get();

        $overdue = Task::query()
            ->whereNull('archived_at')
            ->whereNull('parent_id')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $unassigned = Task::query()
            ->whereNull('archived_at')
            ->whereNull('parent_id')
            ->whereNull('completed_at')
            ->whereDoesntHave('assignees')
            ->count();
        $heavyLoad = $employees->filter(fn ($emp) => $emp->active_count > 5)->count();

        $insight = match (true) {
            $overdue > 0 => "{$overdue} overdue task(s) need escalation. Review the task dashboard and reassign capacity.",
            $unassigned > 0 => "{$unassigned} open task(s) are unassigned. Balance workload from Employee Hub or Task Dashboard.",
            $heavyLoad > 0 => "{$heavyLoad} employee(s) are above the heavy-load threshold (>5 active tasks).",
            default => 'Workload looks balanced. Pipeline capacity is steady with no overdue escalations.',
        };

        return view('livewire.boss-workspace.index', [
            'total_projects' => Project::count(),
            'total_customers' => Customer::count(),
            'total_employees' => Employee::count(),
            'total_tasks' => Task::count(),
            'overdue_tasks' => $overdue,
            'unassigned_tasks' => $unassigned,
            'employees' => $employees,
            'insight' => $insight,
        ]);
    }
}
