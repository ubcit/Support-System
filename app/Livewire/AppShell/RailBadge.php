<?php

namespace App\Livewire\AppShell;

use App\Helpers\RailBadges;
use Livewire\Component;

class RailBadge extends Component
{
    use ListensForAppShellUpdates;

    public string $kind = 'inbox';

    public string $placement = 'rail';

    public bool $isWorkspace = false;

    public function mount(): void
    {
        $this->isWorkspace = request()->is('workspace*');
    }

    public function render()
    {
        $badges = RailBadges::for(auth()->user()?->resolveEmployee(), $this->isWorkspace);
        $count = match ($this->kind) {
            'inbox' => (int) ($badges['inbox_unread'] ?? 0),
            'signup' => (int) ($badges['signup_pending'] ?? 0),
            default => (int) ($badges['tasks_overdue'] ?? 0),
        };

        $label = match ($this->kind) {
            'inbox' => 'unread',
            'signup' => 'pending signups',
            default => 'overdue',
        };

        return view('livewire.app-shell.rail-badge', [
            'count' => $count,
            'label' => $label,
        ]);
    }
}
