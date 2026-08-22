<?php

namespace Modules\Statistics\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Workflows\Events\IssueStateChanged;
use Modules\Statistics\Models\DailySnapshot;

class AggregateIssueMetrics implements ShouldQueue
{
    public function handle(IssueStateChanged $event): void
    {
        $issue = $event->issue;
        $date = now()->toDateString();
        
        $metricKey = 'issues_transitioned_to_' . str_replace(' ', '_', strtolower($event->toState->name));

        // 1. Customer-level Snapshot
        if ($issue->customer_id) {
            $customerSnapshot = DailySnapshot::firstOrCreate(
                ['entity_type' => 'customer', 'entity_id' => $issue->customer_id, 'date' => $date],
                ['metrics' => []]
            );
            $customerSnapshot->incrementMetric($metricKey);
            $customerSnapshot->incrementMetric('issues_active');
        }

        // 2. Project-level Snapshot
        if ($issue->project_id) {
            $projectSnapshot = DailySnapshot::firstOrCreate(
                ['entity_type' => 'project', 'entity_id' => $issue->project_id, 'date' => $date],
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
