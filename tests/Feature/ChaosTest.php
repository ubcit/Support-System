<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Health\Services\HealthEngine;

class ChaosTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_engine_detects_queue_degradation()
    {
        // Mock a scenario where critical queue spikes
        // In a real chaos test, we would fill Redis up or mock the facade.
        
        $engine = app(HealthEngine::class);
        
        // Assuming we replace the check with a mock for testing
        $mockCheck = new class implements \Modules\Health\Contracts\HealthCheckInterface {
            public function getName(): string { return 'queue'; }
            public function check(): string { return 'Critical'; }
            public function getMetadata(): array { return ['critical_size' => 5000]; }
        };

        // Re-register to override
        $engine->registerCheck($mockCheck);
        
        $report = $engine->evaluateHealth();

        $this->assertEquals('Critical', $report['overall_status']);
    }
}
