<?php

namespace Modules\Synchronization\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Synchronization\Contracts\SyncProviderInterface;
use Modules\Synchronization\DTOs\SyncTaskDTO;
use Modules\Tasks\Models\Task;

class GenericSyncProvider implements SyncProviderInterface
{
    public function getName(): string
    {
        return 'generic';
    }

    public function createTask(Model $task): string
    {
        if (!$task instanceof Task) {
            throw new \InvalidArgumentException('GenericSyncProvider only supports Tasks.');
        }

        $dto = SyncTaskDTO::fromTask($task);

        return 'ext_' . Str::random(10);
    }

    public function updateTask(Model $task): void
    {
        // Generic update implementation
    }

    public function syncStatus(Model $task): void
    {
        // Generic status sync implementation
    }
}
