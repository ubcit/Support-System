<?php

namespace Modules\Synchronization\Enums;

enum SyncDirection: string
{
    case Push = 'push';
    case Pull = 'pull';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push',
            self::Pull => 'Pull',
        };
    }
}
