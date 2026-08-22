<?php

namespace Modules\Synchronization\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Conflict = 'conflict';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
            self::Conflict => 'Conflict',
        };
    }
}
