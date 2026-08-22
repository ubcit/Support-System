<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tasks\Models\Task;

class ScheduleController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $start = $request->query('start', now()->startOfMonth()->toDateString());
        $end = $request->query('end', now()->endOfMonth()->toDateString());

        $tasks = Task::query()
            ->whereNotNull('due_date')
            ->whereNull('archived_at')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('due_date', [$start, $end])
                    ->orWhereBetween('start_date', [$start, $end]);
            })
            ->with(['project'])
            ->get();

        $events = $tasks->map(function (Task $task) {
            $priority = $task->priority?->value ?? 'medium';
            $calendar = match ($priority) {
                'urgent', 'high' => 'Danger',
                'low' => 'Success',
                default => 'Primary',
            };

            if ($task->completed_at) {
                $calendar = 'Success';
            }

            return [
                'id' => (string) $task->id,
                'title' => $task->title,
                'start' => ($task->start_date ?? $task->due_date)?->toDateString(),
                'end' => $task->due_date?->copy()->addDay()->toDateString(),
                'allDay' => true,
                'url' => route('task-detail', $task->id),
                'extendedProps' => [
                    'uuid' => $task->uuid,
                    'calendar' => $calendar,
                    'priority' => $priority,
                    'project' => $task->project?->name,
                ],
            ];
        })->values();

        return response()->json($events);
    }

    public function reschedule(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
        ]);

        $task = Task::where('uuid', $uuid)->firstOrFail();
        $task->update([
            'due_date' => $data['due_date'],
            'start_date' => $data['start_date'] ?? $task->start_date,
        ]);

        return response()->json([
            'ok' => true,
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'due_date' => $task->due_date?->toDateString(),
            ],
        ]);
    }
}
