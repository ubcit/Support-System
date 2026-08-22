<?php

namespace Modules\Telemetry\Services;

use Illuminate\Support\Facades\Cache;

class MetricsRegistry
{
    /**
     * Increment a specific metric counter.
     */
    public function increment(string $metric, int $amount = 1): void
    {
        $key = "telemetry:metrics:{$metric}";
        
        if (!Cache::has($key)) {
            Cache::forever($key, 0);
        }
        
        Cache::increment($key, $amount);
    }

    /**
     * Record a timing/gauge metric (e.g. latency).
     * In a real app, this might push to a time-series DB or Prometheus exporter.
     */
    public function record(string $metric, float $value): void
    {
        // Scaffold: Just store the latest value for now
        $key = "telemetry:gauge:{$metric}";
        Cache::put($key, $value, now()->addDays(1));
    }

    /**
     * Retrieve all current metric values.
     */
    public function getMetrics(): array
    {
        // Scaffold: Hardcoded list for demonstration. 
        // In reality, use Redis keys matching pattern.
        $metrics = [
            'webhooks_received_total',
            'tasks_created_total',
            'notifications_sent_total',
            'ai_requests_total',
            'sync_failures_total',
        ];

        $results = [];
        foreach ($metrics as $metric) {
            $results[$metric] = Cache::get("telemetry:metrics:{$metric}", 0);
        }
        
        return $results;
    }
}
