<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Modules\Communication\Models\Conversation;
use Modules\Employees\Models\Employee;
use Modules\Health\Services\HealthEngine;
use Modules\Notifications\Models\Notification;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskActivityLog;

class Index extends Component
{
    public function render(HealthEngine $healthEngine)
    {
        $projects = Project::with('tasks')->get();
        $employees = Employee::with('user')->withCount(['assignments as active_tasks' => function ($q) {
            $q->whereHas('task', fn ($t) => $t->whereNull('completed_at'));
        }])->get();

        $tasks = Task::with('assignees.user')->whereNull('archived_at')->get();
        $unassignedTasks = $tasks->filter(fn ($t) => $t->assignees->count() === 0 && ! $t->completed_at);

        $boss_name = auth()->user()?->name ?? 'there';
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $today_work_count = $tasks->filter(fn ($t) => $t->due_date && $t->due_date->isToday())->count();
        $total_projects = $projects->count();
        $tasks_waiting = $unassignedTasks->count();
        $conversations_waiting = Conversation::where('status', 'open')->count();
        $unread_notifications_count = Notification::whereNull('read_at')->count();
        $overdue_tasks = $tasks->filter(fn ($t) => ! $t->completed_at && $t->due_date && $t->due_date->isPast())->count();
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
