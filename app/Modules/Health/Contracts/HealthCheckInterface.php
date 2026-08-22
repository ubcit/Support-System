<?php

namespace Modules\Health\Contracts;

interface HealthCheckInterface
{
    /**
     * Get the name of this health check (e.g. 'database', 'ai_provider').
     */
    public function getName(): string;

    /**
     * Perform the health check.
     * Returns a status string: 'Healthy', 'Warning', 'Degraded', 'Critical'
     */
    public function check(): string;

    /**
     * Get any additional metadata from the last check (e.g. latency in ms, error messages).
     */
    public function getMetadata(): array;
}
