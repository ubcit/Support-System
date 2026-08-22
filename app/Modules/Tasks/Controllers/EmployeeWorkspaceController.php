<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Notifications\Models\Notification;
use Modules\Tasks\Models\EmployeeTaskState;
use Modules\Tasks\Models\Task;

class EmployeeWorkspaceController extends Controller
{
    public function workspace(Request $request): JsonResponse
    {
        $employee = auth()->user()?->resolveEmployee();

        if (!$employee) {
            return response()->json(['error' => 'Employee profile not found'], 404);
        }

        // Assigned Tasks Query
        $assignedTasksQuery = Task::whereHas('assignees', function ($q) use ($employee) {
            $q->where('employees.id', $employee->id);
        })->whereNull('archived_at');

        $assignedTasks = (clone $assignedTasksQuery)->with(['project', 'checklists.items', 'assignees'])->get();

        $today = now()->toDateString();

        $queue = $assignedTasks->whereNull('completed_at')->values();
        $todayTasks = $queue->filter(fn ($t) => $t->due_date && $t->due_date->toDateString() === $today)->values();
        $upcomingTasks = $queue->filter(fn ($t) => $t->due_date && $t->due_date->toDateString() > $today)->values();
        $overdueTasks = $queue->filter(fn ($t) => $t->isOverdue())->values();
        $completedTasks = $assignedTasks->whereNotNull('completed_at')->values();

        // Bookmarks
        $bookmarkedTaskIds = EmployeeTaskState::where('employee_id', $employee->id)
            ->where('is_bookmarked', true)
            ->pluck('task_id');
        $bookmarkedTasks = Task::whereIn('id', $bookmarkedTaskIds)->get();

        // Notifications & Mentions
        $unreadNotifications = Notification::where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
            'categories' => [
                'my_queue' => $queue,
                'today' => $todayTasks,
                'upcoming' => $upcomingTasks,
                'overdue' => $overdueTasks,
                'completed' => $completedTasks,
                'bookmarks' => $bookmarkedTasks,
            ],
            'unread_notifications' => $unreadNotifications,
            'metrics' => [
                'total_assigned' => $assignedTasks->count(),
                'active_queue_count' => $queue->count(),
                'overdue_count' => $overdueTasks->count(),
                'completed_count' => $completedTasks->count(),
            ],
        ]);
    }

    public function bookmarkTask(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $employee = auth()->user()?->resolveEmployee();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 404);
        }

        $state = EmployeeTaskState::firstOrCreate(
            ['employee_id' => $employee->id, 'task_id' => $task->id],
            ['is_bookmarked' => true]
        );

        $state->update(['is_bookmarked' => !$state->is_bookmarked]);

        return response()->json(['is_bookmarked' => $state->is_bookmarked]);
    }
}
