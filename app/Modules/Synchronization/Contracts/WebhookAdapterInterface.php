<?php

namespace Modules\Synchronization\Contracts;

use Illuminate\Http\Request;
use Modules\Synchronization\DTOs\ProviderEventDTO;

interface WebhookAdapterInterface
{
    /**
     * Get the name of this provider (e.g. 'generic').
     */
    public function getProviderName(): string;

    /**
     * Verify the webhook signature to ensure authenticity.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Normalize the raw HTTP request into a structured ProviderEventDTO.
     */
    public function normalize(Request $request): ProviderEventDTO;
}
