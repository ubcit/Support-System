<?php

namespace Modules\Synchronization\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Synchronization\Models\Synchronization;
use Modules\Synchronization\Services\SynchronizationManager;

class SynchronizeObjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Model $syncable,
        public string $providerName,
        public string $action = 'create' // create, update, delete
    ) {}

    public function handle(SynchronizationManager $manager): void
    {
        Log::info("SynchronizeObjectJob started for {$this->syncable->getMorphClass()} ID {$this->syncable->id} via {$this->providerName}");

        $provider = $manager->getProvider($this->providerName);

        // Idempotency / Sync State Check
        $syncRecord = Synchronization::firstOrCreate([
            'syncable_type' => $this->syncable->getMorphClass(),
            'syncable_id' => $this->syncable->id,
            'provider' => $this->providerName,
        ], [
            'status' => 'pending',
            'attempts' => 0,
        ]);

        if ($syncRecord->status === 'synced' && $this->action === 'create') {
            Log::info("Object already synced. Skipping creation.");
            return;
        }

        $syncRecord->increment('attempts');
        $syncRecord->update(['last_attempt' => now()]);

        try {
            if ($this->action === 'create') {
                $externalId = $provider->createTask($this->syncable);
                
                $syncRecord->update([
                    'provider_object_id' => $externalId,
                    'status' => 'synced',
                    'last_error' => null,
                ]);

                // Update internal task sync_status if applicable
                if (method_exists($this->syncable, 'update')) {
                    $this->syncable->update(['sync_status' => 'synced']);
                }
            }
        } catch (\Exception $e) {
            $syncRecord->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            if (method_exists($this->syncable, 'update')) {
                $this->syncable->update(['sync_status' => 'failed']);
            }

            throw $e; // Trigger retry
        }
    }
}
