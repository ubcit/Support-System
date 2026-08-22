<?php

namespace Modules\Issues\Enums;

enum IssuePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => '#6b7280',
            self::Medium => '#3b82f6',
            self::High => '#f59e0b',
            self::Urgent => '#ef4444',
            self::Critical => '#dc2626',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
            self::Critical => 5,
        };
    }
}
