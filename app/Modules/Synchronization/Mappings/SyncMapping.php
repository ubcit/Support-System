<?php

namespace Modules\Synchronization\Mappings;

class SyncMapping
{
    /**
     * Map internal Workflow State Name to External Provider Status
     */
    public static function taskStateToExternal(?string $stateName): string
    {
        if (!$stateName) return 'Open';
        
        $normalized = strtolower($stateName);

        return match ($normalized) {
            'todo', 'new', 'pending', 'open' => 'Open',
            'in progress', 'started' => 'In Progress',
            'review', 'code review', 'qa' => 'Review',
            'completed', 'verified', 'done' => 'Complete',
            'archived', 'closed', 'cancelled' => 'Closed',
            default => 'Open',
        };
    }

    /**
     * Map internal Assignment Status to External Provider Status
     */
    public static function assignmentStatusToExternal(string $internalStatus): string
    {
        return match ($internalStatus) {
            'pending'  => 'Open',
            'accepted' => 'In Progress',
            'started'  => 'In Progress',
            'blocked'  => 'Blocked',
            'completed'=> 'Complete',
            default    => 'Open',
        };
    }
}
