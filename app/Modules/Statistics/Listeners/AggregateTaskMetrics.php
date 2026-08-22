<?php

namespace Modules\Statistics\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Workflows\Events\TaskStateChanged;
use Modules\Statistics\Models\DailySnapshot;

class AggregateTaskMetrics implements ShouldQueue
{
    public function handle(TaskStateChanged $event): void
    {
        $task = $event->task;
        $date = now()->toDateString();
        
        $metricKey = 'tasks_transitioned_to_' . str_replace(' ', '_', strtolower($event->toState->name));

        // 1. Employee-level Snapshot
        if ($task->assigned_to) {
            $employeeSnapshot = DailySnapshot::firstOrCreate(
                ['entity_type' => 'employee', 'entity_id' => $task->assigned_to, 'date' => $date],
                ['metrics' => []]
            );
            $employeeSnapshot->incrementMetric($metricKey);
            $employeeSnapshot->incrementMetric('tasks_active');
        }

        // 2. Project-level Snapshot
        if ($task->project_id) {
            $projectSnapshot = DailySnapshot::firstOrCreate(
                ['entity_type' => 'project', 'entity_id' => $task->project_id, 'date' => $date],
                ['metrics' => []]
            );
            $projectSnapshot->incrementMetric($metricKey);
        }

        // 3. System-level Snapshot
        $systemSnapshot = DailySnapshot::firstOrCreate(
            ['entity_type' => 'system', 'entity_id' => null, 'date' => $date],
            ['metrics' => []]
        );
        $systemSnapshot->incrementMetric($metricKey);
    }
}
