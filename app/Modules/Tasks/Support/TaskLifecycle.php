<?php

namespace Modules\Tasks\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;

class TaskLifecycle
{
    /**
     * Cache: workflow_id => [workflow_state_id => TaskStatus]
     *
     * The mapping is derived from WorkflowState.name using the same logic as
     * Task::getStatusAttribute(), so we can convert state IDs in activity logs
     * back into task statuses.
     *
     * @var array<int, array<int, TaskStatus>>
     */
    private static array $workflowStateStatusCache = [];

    /**
     * @return array<int, array{
     *   status: TaskStatus,
     *   label: string,
     *   entered_at: Carbon,
     *   exited_at: ?Carbon,
     *   seconds: int,
     *   duration_label: string,
     *   is_current: bool
     * }>
     */
    public function segments(Task $task, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $cycleEnd = $task->completed_at ?? $asOf;
        $createdAt = $task->created_at instanceof Carbon ? $task->created_at : Carbon::parse($task->created_at);

        $logs = $task->relationLoaded('activityLogs')
            ? $task->activityLogs
            : $task->activityLogs()->get();

        $transitions = $this->statusTransitions($task, $logs);

        // No status move logs: treat the whole lifecycle as "current status".
        if ($transitions->isEmpty()) {
            $status = $task->status ?? TaskStatus::Todo;
            $seconds = max(0, $createdAt->diffInSeconds($cycleEnd));

            return [[
                'status' => $status,
                'label' => $status->label(),
                'entered_at' => $createdAt,
                'exited_at' => $task->completed_at ? $cycleEnd : null,
                'seconds' => $seconds,
                'duration_label' => $this->format($seconds),
                'is_current' => $task->completed_at === null,
            ]];
        }

        $initialStatus = $transitions->first()['from'] ?? $task->status;
        if (! $initialStatus instanceof TaskStatus) {
            $initialStatus = $task->status ?? TaskStatus::Todo;
        }

        $segments = [];
        $currentStatus = $initialStatus;
        $segmentStart = $createdAt;

        /** @var array{at:Carbon,from:?TaskStatus,to:?TaskStatus} $transition */
        foreach ($transitions as $transition) {
            $segmentEnd = $transition['at'];

            if ($segmentEnd->lessThan($segmentStart)) {
                continue;
            }

            $seconds = max(0, $segmentStart->diffInSeconds($segmentEnd));
            if ($seconds > 0) {
                $segments[] = [
                    'status' => $currentStatus,
                    'label' => $currentStatus->label(),
                    'entered_at' => $segmentStart,
                    'exited_at' => $segmentEnd,
                    'seconds' => $seconds,
                    'duration_label' => $this->format($seconds),
                    'is_current' => false,
                ];
            }

            if ($transition['to'] instanceof TaskStatus) {
                $currentStatus = $transition['to'];
            }
            $segmentStart = $segmentEnd;
        }

        // Final segment: current state until completion, or until $asOf.
        $seconds = max(0, $segmentStart->diffInSeconds($cycleEnd));
        $segments[] = [
            'status' => $currentStatus,
            'label' => $currentStatus->label(),
            'entered_at' => $segmentStart,
            'exited_at' => $task->completed_at ? $cycleEnd : null,
            'seconds' => $seconds,
            'duration_label' => $this->format($seconds),
            'is_current' => $task->completed_at === null,
        ];

        return $segments;
    }

    public function cycleSeconds(Task $task, ?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? now();
        $cycleEnd = $task->completed_at ?? $asOf;
        $createdAt = $task->created_at instanceof Carbon ? $task->created_at : Carbon::parse($task->created_at);

        return max(0, $createdAt->diffInSeconds($cycleEnd));
    }

    public function currentStatusSeconds(Task $task, ?Carbon $asOf = null): int
    {
        $segments = $this->segments($task, $asOf);

        return (int) ($segments ? $segments[array_key_last($segments)]['seconds'] : 0);
    }

    public function format(int $seconds): string
    {
        $seconds = max(0, $seconds);

        // Normalize to minutes for stable UX. This keeps values like "60s"
        // from turning into "1m" vs "0m" depending on rounding rules.
        $totalMinutes = (int) round($seconds / 60);
        $days = intdiv($totalMinutes, 1440);
        $remainingMinutes = $totalMinutes % 1440;
        $hours = intdiv($remainingMinutes, 60);
        $minutes = $remainingMinutes % 60;

        if ($days > 0) {
            $out = $days.'d';
            if ($hours > 0) {
                $out .= ' '.$hours.'h';
            }

            return $out;
        }

        if ($hours > 0) {
            $out = $hours.'h';
            if ($minutes > 0) {
                $out .= ' '.$minutes.'m';
            }

            return $out;
        }

        return $minutes.'m';
    }

    /**
     * Extract workflow transitions from Task activity logs.
     *
     * We treat these as state changes:
     * - `state_moved` / `bulk_status_changed` where old/new values are workflow_state_id
     * - `field_updated` for `current_state_id` (state id values)
     * - `field_updated` for `status` (TaskStatus enum values)
     *
     * @return Collection<int, array{at:Carbon,from:?TaskStatus,to:?TaskStatus}>
     */
    private function statusTransitions(Task $task, $logs): Collection
    {
        $workflowStateStatus = $this->workflowStateIdToStatusMap($task);

        $filtered = collect($logs)
            ->filter(function ($log) {
                if (! isset($log->action)) {
                    return false;
                }

                $action = (string) $log->action;
                $field = $log->field;

                return match ($action) {
                    'state_moved', 'bulk_status_changed' => true,
                    'field_updated' => in_array((string) $field, ['status', 'current_state_id'], true),
                    default => false,
                };
            })
            ->sortBy(fn ($l) => $l->created_at);

        $transitions = collect();

        foreach ($filtered as $log) {
            $action = (string) $log->action;
            $field = $log->field;
            $oldValue = $log->old_value;
            $newValue = $log->new_value;

            $fromStatus = null;
            $toStatus = null;

            if ($action === 'state_moved' || $action === 'bulk_status_changed') {
                $fromId = is_numeric($oldValue) ? (int) $oldValue : null;
                $toId = is_numeric($newValue) ? (int) $newValue : null;
                $fromStatus = $fromId !== null ? ($workflowStateStatus[$fromId] ?? null) : null;
                $toStatus = $toId !== null ? ($workflowStateStatus[$toId] ?? null) : null;
            } elseif ($action === 'field_updated' && (string) $field === 'current_state_id') {
                $fromId = is_numeric($oldValue) ? (int) $oldValue : null;
                $toId = is_numeric($newValue) ? (int) $newValue : null;
                $fromStatus = $fromId !== null ? ($workflowStateStatus[$fromId] ?? null) : null;
                $toStatus = $toId !== null ? ($workflowStateStatus[$toId] ?? null) : null;
            } elseif ($action === 'field_updated' && (string) $field === 'status') {
                $fromStatus = $oldValue !== null ? TaskStatus::tryFrom((string) $oldValue) : null;
                $toStatus = $newValue !== null ? TaskStatus::tryFrom((string) $newValue) : null;
            }

            if (! ($fromStatus instanceof TaskStatus) || ! ($toStatus instanceof TaskStatus)) {
                continue;
            }

            // Ignore "no-op" transitions.
            if ($fromStatus === $toStatus) {
                continue;
            }

            $transitions->push([
                'at' => $log->created_at instanceof Carbon ? $log->created_at : Carbon::parse($log->created_at),
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);
        }

        return $transitions->values();
    }

    /**
     * @return array<int, TaskStatus> workflow_state_id => TaskStatus
     */
    private function workflowStateIdToStatusMap(Task $task): array
    {
        $workflowId = (int) ($task->workflow_id ?? 0);
        if (! $workflowId) {
            return [];
        }

        if (isset(self::$workflowStateStatusCache[$workflowId])) {
            return self::$workflowStateStatusCache[$workflowId];
        }

        $states = WorkflowState::query()
            ->where('workflow_id', $workflowId)
            ->select(['id', 'name'])
            ->get();

        $map = [];

        foreach ($states as $state) {
            $slug = Str::slug((string) $state->name, '_');

            $normalized = match ($slug) {
                'to_do', 'todo' => TaskStatus::Todo->value,
                'in_progress' => TaskStatus::InProgress->value,
                'review', 'code_review', 'in_review' => TaskStatus::Review->value,
                'done' => TaskStatus::Done->value,
                'cancelled', 'canceled' => TaskStatus::Cancelled->value,
                default => $slug,
            };

            $status = TaskStatus::tryFrom($normalized);
            if ($status instanceof TaskStatus) {
                $map[(int) $state->id] = $status;
            }
        }

        self::$workflowStateStatusCache[$workflowId] = $map;

        return $map;
    }
}
