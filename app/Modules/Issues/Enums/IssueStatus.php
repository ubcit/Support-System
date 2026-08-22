<?php

namespace Modules\Issues\Enums;

enum IssueStatus: string
{
    case New = 'new';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Waiting => 'Waiting',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => '#6366f1',
            self::Open => '#3b82f6',
            self::InProgress => '#f59e0b',
            self::Waiting => '#8b5cf6',
            self::Resolved => '#10b981',
            self::Closed => '#6b7280',
        };
    }
}
