<?php

namespace App\Livewire\Issues;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\Issues\Enums\IssueStatus;
use Modules\Issues\Models\Issue;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $statusFilter = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Issue::with(['project', 'customer'])
            ->withCount('tasks')
            ->latest();

        if (trim($this->search) !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $s)->orWhere('description', 'like', $s));
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return view('livewire.issues.index', [
            'issues' => $query->paginate(20),
            'statuses' => IssueStatus::cases(),
        ]);
    }
}
