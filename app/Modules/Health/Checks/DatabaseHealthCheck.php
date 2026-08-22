<?php

namespace Modules\Health\Checks;

use Illuminate\Support\Facades\DB;
use Modules\Health\Contracts\HealthCheckInterface;

class DatabaseHealthCheck implements HealthCheckInterface
{
    protected array $metadata = [];

    public function getName(): string
    {
        return 'database';
    }

    public function check(): string
    {
        $start = microtime(true);
        
        try {
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);
            $this->metadata = ['latency_ms' => $latency];
            
            if ($latency > 500) {
                return 'Warning';
            }
            if ($latency > 2000) {
                return 'Degraded';
            }
            
            return 'Healthy';
        } catch (\Exception $e) {
            $this->metadata = ['error' => $e->getMessage()];
            return 'Critical';
        }
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
