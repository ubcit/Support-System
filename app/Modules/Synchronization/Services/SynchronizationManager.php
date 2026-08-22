<?php

namespace Modules\Synchronization\Services;

use Modules\Synchronization\Contracts\SyncProviderInterface;

class SynchronizationManager
{
    protected array $providers = [];

    public function registerProvider(SyncProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function getProvider(string $name): SyncProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \Exception("Synchronization Provider [{$name}] not registered.");
        }

        return $this->providers[$name];
    }
}
