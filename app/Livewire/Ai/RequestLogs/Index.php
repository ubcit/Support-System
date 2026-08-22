<?php

namespace App\Livewire\Ai\RequestLogs;

use App\Models\AiRequestLog;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AiRequestLog::with('aiPrompt')
            ->when($this->search, function ($query) {
                $query->where('uuid', 'like', '%' . $this->search . '%')
                      ->orWhere('model_name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.ai.request-logs.index', [
            'logs' => $logs,
        ]);
    }
}
