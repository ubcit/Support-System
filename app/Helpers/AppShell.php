<?php

namespace App\Helpers;

use Livewire\Component;
use Livewire\Livewire;

class AppShell
{
    public const UPDATED_EVENT = 'app-shell-updated';

    /**
     * Ask every app-shell Livewire panel (secondary sidebar, flyout, rail
     * badges) to re-query. No-ops outside a Livewire request so queued jobs
     * and the REST API never try to talk to a browser.
     *
     * Deduped per HTTP request so bulk creates/deletes don't queue N identical
     * events for the same UI refresh.
     */
    public static function refresh(): void
    {
        InboxCounts::forget();
        RailBadges::forget();
        TaskSidebarCounts::forget();

        if (request()->attributes->get('app-shell.refresh-queued')) {
            return;
        }

        $component = Livewire::current();
        if (! $component instanceof Component) {
            return;
        }

        request()->attributes->set('app-shell.refresh-queued', true);
        $component->dispatch(self::UPDATED_EVENT);
    }
}
