<?php

namespace App\Helpers;

class InboxNav
{
    public static function url(
        ?string $tab = null,
        ?int $conversationId = null,
        ?string $q = null,
        ?int $sessionId = null,
        ?int $customerId = null,
    ): string {
        if ($tab === 'closed') {
            $tab = 'done';
        }

        $query = array_filter([
            'tab' => $tab && $tab !== 'all' ? $tab : null,
            'customer' => $customerId,
            'conversation' => $conversationId,
            'session' => $sessionId,
            'q' => is_string($q) && trim($q) !== '' ? $q : null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('conversation-center', $query);
    }
}
