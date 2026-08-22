<?php

namespace App\Contracts;

interface SyncProviderInterface
{
    /**
     * Creates a task in the remote system and returns its ID.
     *
     * @param array $data
     * @return string
     */
    public function createTask(array $data): string;
}
