<?php

namespace App\Livewire\Ai\RequestLogs;

use App\Models\AiRequestLog;
use Livewire\Component;

class View extends Component
{
    public AiRequestLog $log;

    public function mount(AiRequestLog $log)
    {
        $this->log = $log;
    }

    public function render()
    {
        return view('livewire.ai.request-logs.view');
    }
}
