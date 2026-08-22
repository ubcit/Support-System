<?php

namespace Modules\Security\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookSignatureFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $provider,
        public string $ipAddress,
        public array $headers
    ) {}
}
