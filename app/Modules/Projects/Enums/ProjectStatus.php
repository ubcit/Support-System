<?php

namespace Modules\Projects\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => '#10b981',
            self::Paused => '#f59e0b',
            self::Completed => '#3b82f6',
            self::Archived => '#6b7280',
        };
    }
}
