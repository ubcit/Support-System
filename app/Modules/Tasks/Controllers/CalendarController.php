<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Models\Task;

class CalendarController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $tasks = Task::whereNotNull('due_date')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->whereNull('archived_at')
            ->with(['assignees', 'project'])
            ->get();

        $events = $tasks->map(fn ($task) => [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'start' => $task->start_date?->toDateString() ?? $task->due_date->toDateString(),
            'end' => $task->due_date->toDateString(),
            'priority' => $task->priority?->value ?? 'medium',
            'is_completed' => (bool) $task->completed_at,
            'recurrence_rule' => $task->recurrence_rule,
        ]);

        return response()->json(['data' => $events]);
    }

    public function reschedule(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $newDueDate = $request->input('due_date');

        $task->update([
            'due_date' => $newDueDate,
            'start_date' => $request->input('start_date', $task->start_date),
        ]);

        return response()->json(['data' => $task]);
    }
}
