<?php

namespace App\Livewire\WorkerLogs;

use App\Livewire\Concerns\ReadsStorageLogFile;
use Livewire\Component;

class Index extends Component
{
    use ReadsStorageLogFile;

    /** @var 'worker'|'shadow' */
    public string $source = 'worker';

    public function mount(): void
    {
        $this->loadLog();
    }

    public function updatedSource(): void
    {
        $this->loadLog();
    }

    public function render()
    {
        return view('livewire.worker-logs.index');
    }

    protected function logPath(): string
    {
        return match ($this->source) {
            'shadow' => storage_path('logs/shadow-worker.log'),
            default => storage_path('logs/worker.log'),
        };
    }

    protected function logLabel(): string
    {
        return match ($this->source) {
            'shadow' => 'Shadow worker log',
            default => 'Worker log',
        };
    }
}
