<?php

namespace App\Helpers;

use App\Models\User;
use Modules\Authentication\Services\SignupRequestNotifier;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;

class RailBadges
{
    /**
     * Counts shown on the primary rail icons. Request-cached.
     *
     * @return array{inbox_unread: int, tasks_overdue: int, signup_pending: int}
     */
    public static function for(?Employee $actor = null, ?bool $isWorkspace = null): array
    {
        $isWorkspace = $isWorkspace ?? request()->is('workspace*');
        $viewer = $actor?->user ?? auth()->user();
        $key = 'rail.badges.'.($actor?->id ?? 'anon').'.'.($viewer?->id ?? '0').'.'.($isWorkspace ? '1' : '0');

        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $today = now()->toDateString();

        $overdue = Task::query()
            ->whereNull('archived_at')
            ->whereNull('parent_id')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        if ($isWorkspace) {
            if ($actor) {
                $overdue->whereHas('assignees', fn ($query) => $query->where('employees.id', $actor->id));
            } else {
                $overdue->whereRaw('1 = 0');
            }
        }

        $payload = [
            'inbox_unread' => $isWorkspace ? 0 : InboxCounts::unread(),
            'tasks_overdue' => (int) $overdue->count(),
            'signup_pending' => $isWorkspace ? 0 : SignupRequestNotifier::pendingCountFor($viewer instanceof User ? $viewer : null),
        ];

        request()->attributes->set($key, $payload);

        return $payload;
    }

    /**
     * Drop request-local rail counts after inbox/task writes.
     */
    public static function forget(): void
    {
        foreach (array_keys(request()->attributes->all()) as $key) {
            if (is_string($key) && str_starts_with($key, 'rail.badges.')) {
                request()->attributes->remove($key);
            }
        }
    }
}
