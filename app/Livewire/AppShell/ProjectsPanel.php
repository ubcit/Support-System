<?php

namespace App\Livewire\AppShell;

use App\Helpers\ProjectNavHelper;
use Livewire\Component;

class ProjectsPanel extends Component
{
    use ListensForAppShellUpdates;

    public ?string $selectedProjectId = null;

    public function mount(): void
    {
        $this->selectedProjectId = request()->filled('project') ? (string) request('project') : null;
    }

    public function render()
    {
        return view('livewire.app-shell.projects-panel', [
            'sidebarProjects' => ProjectNavHelper::getProjects(),
        ]);
    }
}
