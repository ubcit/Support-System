<?php

namespace Modules\Issues\Enums;

enum IssueSource: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Web = 'web';
    case App = 'app';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Web => 'Web',
            self::App => 'Mobile App',
            self::Manual => 'Manual',
        };
    }
}
