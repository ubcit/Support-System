<?php

namespace Modules\Employees\Enums;

enum SkillLevel: string
{
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Expert = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Mid => 'Mid-Level',
            self::Senior => 'Senior',
            self::Expert => 'Expert',
        };
    }
}
