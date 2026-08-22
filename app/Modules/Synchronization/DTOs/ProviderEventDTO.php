<?php

namespace Modules\Synchronization\DTOs;

class ProviderEventDTO
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalEventId,
        public readonly string $eventType, // e.g. task_status_changed, comment_added
        public readonly string $objectType, // e.g. task
        public readonly string $providerObjectId, // e.g. external provider task id
        public readonly array $payload, // The normalized or raw payload if needed
        public readonly ?string $actorId = null, // The provider's user ID who performed it
    ) {}
}
