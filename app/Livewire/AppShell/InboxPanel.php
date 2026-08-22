<?php

namespace App\Livewire\AppShell;

use App\Helpers\InboxCounts;
use Livewire\Component;

class InboxPanel extends Component
{
    use ListensForAppShellUpdates;

    public string $tab = 'all';

    public string $q = '';

    public ?int $selectedId = null;

    public ?int $selectedSessionId = null;

    public ?int $selectedCustomerId = null;

    public function mount(): void
    {
        $tab = request('tab', 'all');
        if ($tab === 'closed') {
            $tab = 'done';
        }
        $this->tab = in_array($tab, ['all', 'unread', 'done', 'review'], true) ? $tab : 'all';
        $this->q = trim((string) request('q', ''));
        $this->selectedId = request()->filled('conversation') ? (int) request('conversation') : null;
        $this->selectedSessionId = request()->filled('session') ? (int) request('session') : null;
        $this->selectedCustomerId = request()->filled('customer') ? (int) request('customer') : null;
    }

    public function render()
    {
        $inbox = InboxCounts::panel(
            $this->tab,
            $this->q,
            $this->selectedId,
            $this->selectedSessionId,
            $this->selectedCustomerId,
        );

        return view('livewire.app-shell.inbox-panel', [
            'inbox' => $inbox,
        ]);
    }
}
