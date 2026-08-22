<?php

namespace Modules\Tasks\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Review => 'In Review',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => '#6b7280',
            self::InProgress => '#f59e0b',
            self::Review => '#8b5cf6',
            self::Done => '#10b981',
            self::Cancelled => '#ef4444',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Done, self::Cancelled]);
    }
}
