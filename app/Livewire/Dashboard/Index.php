<?php

namespace App\Livewire\Dashboard;

use App\Helpers\TaskSidebarCounts;
use Livewire\Component;
use Modules\Communication\Models\Conversation;
use Modules\Employees\Models\Employee;
use Modules\Health\Services\HealthEngine;
use Modules\Notifications\Models\Notification;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\TaskActivityLog;

class Index extends Component
{
    public function render(HealthEngine $healthEngine)
    {
        $actor = auth()->user()?->resolveEmployee();
        $counts = TaskSidebarCounts::for($actor);

        $projects = Project::with('tasks')->get();
        $employees = Employee::with('user')->withCount(['assignments as active_tasks' => function ($q) {
            $q->whereHas('task', fn ($t) => $t->whereNull('completed_at'));
        }])->get();

        $boss_name = auth()->user()?->name ?? 'there';
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $today_work_count = $counts['today'];
        $total_projects = $projects->count();
        $tasks_waiting = $counts['unassigned'];
        $conversations_waiting = Conversation::where('status', 'open')->count();
        $unread_notifications_count = Notification::whereNull('read_at')->count();
        $overdue_tasks = $counts['overdue'];
        $system_health = $healthEngine->runAll();
        $recent_activity = TaskActivityLog::with(['task', 'employee'])->orderBy('created_at', 'desc')->limit(8)->get();

        return view('livewire.dashboard.index', compact(
            'boss_name',
            'greeting',
            'today_work_count',
            'total_projects',
            'employees',
            'tasks_waiting',
            'conversations_waiting',
            'unread_notifications_count',
            'overdue_tasks',
            'system_health',
            'recent_activity'
        ));
    }
}
