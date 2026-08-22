<?php

namespace App\Livewire\PipelineLogs;

use App\Models\PipelineLog;
use Livewire\Component;

class View extends Component
{
    public PipelineLog $log;

    public function mount(PipelineLog $log)
    {
        $this->log = $log;
    }

    public function render()
    {
        return view('livewire.pipeline-logs.view');
    }
}
