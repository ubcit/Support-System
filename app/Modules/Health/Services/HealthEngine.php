<?php

namespace Modules\Health\Services;

use Modules\Health\Contracts\HealthCheckInterface;
use Modules\Health\Events\SystemHealthDegraded;
use Illuminate\Support\Facades\Cache;

class HealthEngine
{
    protected array $checks = [];
    
    // Severity mapping to determine overall platform health
    protected array $severity = [
        'Healthy' => 0,
        'Warning' => 1,
        'Degraded' => 2,
        'Critical' => 3,
    ];

    public function registerCheck(HealthCheckInterface $check): void
    {
        $this->checks[$check->getName()] = $check;
    }

    public function runAll(): array
    {
        return $this->evaluateHealth();
    }

    public function evaluateHealth(): array
    {
        $overallStatus = 'Healthy';
        $maxSeverity = 0;
        $report = [];

        foreach ($this->checks as $name => $check) {
            $status = $check->check();
            $severity = $this->severity[$status] ?? 3;

            $report[$name] = [
                'status' => $status,
                'metadata' => $check->getMetadata(),
            ];

            if ($severity > $maxSeverity) {
                $maxSeverity = $severity;
                $overallStatus = $status;
            }
        }

        $finalReport = [
            'timestamp' => now()->toIso8601String(),
            'overall_status' => $overallStatus,
            'checks' => $report,
        ];

        $this->detectStateChange($overallStatus, $finalReport);

        return $finalReport;
    }

    protected function detectStateChange(string $currentStatus, array $report): void
    {
        // Cache the last known state to detect transitions
        $lastStatus = Cache::get('platform_health_status', 'Healthy');

        if ($currentStatus !== $lastStatus) {
            Cache::put('platform_health_status', $currentStatus);
            
            // If the state is degrading, fire the domain event so Rules Engine can intercept
            if ($this->severity[$currentStatus] > $this->severity[$lastStatus]) {
                SystemHealthDegraded::dispatch($lastStatus, $currentStatus, $report);
            }
        }
    }
}
