<?php

namespace Modules\Tasks\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Synchronization\Jobs\SynchronizeObjectJob;
use Modules\Tasks\Events\TaskCreated;

class SyncTaskToExternalProvider implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(TaskCreated $event): void
    {
        $providerName = config('services.sync.default_provider', 'generic');
        
        // Dispatch the synchronization job asynchronously
        SynchronizeObjectJob::dispatch($event->task, $providerName, 'create');
    }
}
