<?php

namespace App\Livewire\PipelineLogs;

use App\Models\PipelineLog;
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

    public function delete($id)
    {
        $log = PipelineLog::findOrFail($id);
        $log->delete();
        session()->flash('success', 'Pipeline log deleted successfully.');
    }

    public function render()
    {
        $logs = PipelineLog::when($this->search, function ($query) {
                $query->where('uuid', 'like', '%' . $this->search . '%')
                      ->orWhere('correlation_id', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.pipeline-logs.index', [
            'logs' => $logs,
        ]);
    }
}
