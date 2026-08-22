<?php

namespace Modules\Health\Checks;

use Illuminate\Support\Facades\Queue;
use Modules\Health\Contracts\HealthCheckInterface;

class QueueHealthCheck implements HealthCheckInterface
{
    protected array $metadata = [];

    public function getName(): string
    {
        return 'queue';
    }

    public function check(): string
    {
        try {
            // Scaffold: In production, check Redis/RabbitMQ queue sizes
            $defaultSize = Queue::size('default');
            $criticalSize = Queue::size('critical');
            
            $this->metadata = [
                'default_queue_size' => $defaultSize,
                'critical_queue_size' => $criticalSize,
            ];

            if ($criticalSize > 1000) {
                return 'Critical';
            }
            
            if ($defaultSize > 5000) {
                return 'Degraded';
            }

            if ($defaultSize > 1000) {
                return 'Warning';
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
