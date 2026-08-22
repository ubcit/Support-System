<?php

namespace Modules\Synchronization\Contracts;

use Illuminate\Database\Eloquent\Model;

interface SyncProviderInterface
{
    /**
     * Create a task in the external provider.
     * Returns the external provider's object ID.
     */
    public function createTask(Model $task): string;

    /**
     * Update an existing task in the external provider.
     */
    public function updateTask(Model $task): void;

    /**
     * Sync the current status of the task.
     */
    public function syncStatus(Model $task): void;

    /**
     * Get the name of this provider (e.g., 'generic').
     */
    public function getName(): string;
}
