<?php

namespace Modules\Communication\Enums;

enum MessageChannel: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Telegram = 'telegram';
    case Web = 'web';
    case App = 'app';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Telegram => 'Telegram',
            self::Web => 'Web',
            self::App => 'Mobile App',
        };
    }
}
