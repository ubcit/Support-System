<?php

namespace App\Services\Mock;

use App\Contracts\SyncProviderInterface;
use Illuminate\Support\Str;

class MockSyncProvider implements SyncProviderInterface
{
    protected bool $chaosMode;

    public function __construct(bool $chaosMode = false)
    {
        $this->chaosMode = $chaosMode;
    }

    public function createTask(array $data): string
    {
        // Chaos mode latency: up to 3s. Normal latency: keep short for simulator/tests.
        $maxLatency = $this->chaosMode ? 3000000 : 200000;
        usleep(rand(50000, $maxLatency));

        // Only fail when chaos mode is explicitly enabled.
        if ($this->chaosMode && rand(1, 100) <= 40) {
            throw new \Exception('Chaos: Sync Provider Deadlock or 503 Service Unavailable');
        }

        return 'ext_'.Str::random(8);
    }
}
