<?php

namespace App\Livewire\OperationsDashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;
use Modules\Health\Services\HealthEngine;
use Modules\Telemetry\Services\MetricsRegistry;

class Index extends Component
{
    public function render(HealthEngine $healthEngine, MetricsRegistry $metricsRegistry)
    {
        $health = $healthEngine->evaluateHealth();
        $counters = $metricsRegistry->getMetrics();

        $dbHealthy = false;
        try {
            $dbHealthy = (bool) DB::connection()->getPdo();
        } catch (\Exception $e) {
        }

        $redisHealthy = false;
        try {
            $redisHealthy = (bool) Redis::connection();
        } catch (\Exception $e) {
        }

        return view('livewire.operations-dashboard.index', [
            'health' => $health,
            'ai_requests_today' => $counters['ai_requests_total'] ?? 0,
            'system_status' => [
                'Database' => $dbHealthy ? 'Healthy' : 'Down',
                'Queues (Redis)' => $redisHealthy ? 'Healthy' : 'Down',
            ],
        ]);
    }
}
