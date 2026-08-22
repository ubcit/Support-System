<?php

namespace Modules\Synchronization\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Synchronization\Contracts\WebhookAdapterInterface;
use Modules\Synchronization\Models\ProviderEvent;
use Modules\Synchronization\Jobs\ProcessProviderEventJob;

class ProviderWebhookController extends Controller
{
    protected array $adapters = [];

    public function __construct()
    {
        $this->adapters['generic'] = new \Modules\Synchronization\Adapters\GenericWebhookAdapter();
    }

    public function handle(Request $request, string $providerName)
    {
        if (!isset($this->adapters[$providerName])) {
            Log::warning("Received webhook for unknown provider: {$providerName}");
            return response()->json(['error' => 'Unknown provider'], 400);
        }

        /** @var WebhookAdapterInterface $adapter */
        $adapter = $this->adapters[$providerName];

        if (!$adapter->verifySignature($request)) {
            Log::warning("Invalid webhook signature for provider: {$providerName}");
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $dto = $adapter->normalize($request);
            
            // Store raw event for idempotency and replay
            $eventRecord = ProviderEvent::firstOrCreate(
                [
                    'provider' => $dto->provider,
                    'external_event_id' => $dto->externalEventId,
                ],
                [
                    'event_type' => $dto->eventType,
                    'payload' => $dto->payload,
                    'status' => 'pending',
                ]
            );

            // If already processed, return 200 early (idempotency)
            if ($eventRecord->status === 'processed') {
                return response()->json(['status' => 'already_processed']);
            }

            // Dispatch job asynchronously
            ProcessProviderEventJob::dispatch($eventRecord, $dto);

        } catch (\Exception $e) {
            Log::error("Failed to normalize webhook for {$providerName}: " . $e->getMessage());
            return response()->json(['error' => 'Normalization failed'], 400);
        }

        return response()->json(['status' => 'accepted']);
    }
}
