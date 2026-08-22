<?php

namespace Modules\Synchronization\Adapters;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Synchronization\Contracts\WebhookAdapterInterface;
use Modules\Synchronization\DTOs\ProviderEventDTO;

class GenericWebhookAdapter implements WebhookAdapterInterface
{
    public function getProviderName(): string
    {
        return 'generic';
    }

    public function verifySignature(Request $request): bool
    {
        $secret = config('services.sync.webhook_secret');
        if (!$secret) {
            return true;
        }

        $signature = $request->header('X-Signature');
        $hash = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($signature, $hash);
    }

    public function normalize(Request $request): ProviderEventDTO
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';
        $providerObjectId = $payload['task_id'] ?? $payload['object_id'] ?? null;

        if (!$providerObjectId) {
            throw new \Exception('Missing object_id in webhook payload');
        }

        $externalEventId = $payload['webhook_id'] ?? Str::uuid()->toString();

        return new ProviderEventDTO(
            provider: $this->getProviderName(),
            externalEventId: $externalEventId,
            eventType: $event,
            objectType: 'task',
            providerObjectId: $providerObjectId,
            payload: $payload,
            actorId: $payload['actor_id'] ?? null
        );
    }
}
