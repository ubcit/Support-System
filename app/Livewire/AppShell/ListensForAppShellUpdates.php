<?php

namespace App\Livewire\AppShell;

use App\Helpers\AppShell;
use Livewire\Attributes\On;

trait ListensForAppShellUpdates
{
    #[On(AppShell::UPDATED_EVENT)]
    public function refreshAppShell(): void
    {
        // Re-render with fresh queries. Filter/tab state lives on this
        // component so Livewire's /livewire/update request does not clobber it.
    }
}
