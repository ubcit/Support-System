<?php

namespace Modules\Communication\Support;

use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;

class CustomerLocale
{
    public const EN = 'en';

    public const AR = 'ar';

    /** @var list<string> */
    public const SUPPORTED = [self::EN, self::AR];

    public static function detect(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $text = trim($text);

        if (preg_match('/\p{Arabic}/u', $text) === 1) {
            return self::AR;
        }

        // Standalone project codes / pins — not enough to lock language.
        if (preg_match('/^[A-Za-z0-9_-]{1,12}$/', $text) === 1) {
            return null;
        }

        if (preg_match('/[A-Za-z]/', $text) === 1) {
            return self::EN;
        }

        return null;
    }

    public static function normalize(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));

        return in_array($locale, self::SUPPORTED, true) ? $locale : self::EN;
    }

    /**
     * Lock locale once per session from the first inbound text with a detectable language.
     * Code-only / empty messages leave the session unlocked so a later message can set it.
     */
    public static function resolveForSession(
        ConversationSession $session,
        ?string $inboundText,
        ?Customer $customer = null,
    ): string {
        if (filled($session->customer_locale)) {
            return self::normalize($session->customer_locale);
        }

        $customer ??= $session->relationLoaded('conversation')
            ? $session->conversation?->customer
            : $session->conversation()->with('customer')->first()?->customer;

        $detected = self::detect($inboundText);

        if ($detected === null) {
            return self::normalize($customer?->preferred_locale);
        }

        $session->update(['customer_locale' => $detected]);

        if ($customer && blank($customer->preferred_locale)) {
            $customer->update(['preferred_locale' => $detected]);
        }

        return $detected;
    }

    public static function forReply(ConversationSession $session, ?Customer $customer = null): string
    {
        if (filled($session->customer_locale)) {
            return self::normalize($session->customer_locale);
        }

        $customer ??= $session->conversation?->customer;

        return self::normalize($customer?->preferred_locale);
    }
}
