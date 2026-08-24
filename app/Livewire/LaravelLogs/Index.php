<?php

namespace App\Livewire\LaravelLogs;

use App\Livewire\Concerns\ReadsStorageLogFile;
use Livewire\Component;

class Index extends Component
{
    use ReadsStorageLogFile;

    public function mount(): void
    {
        $this->loadLog();
    }

    public function render()
    {
        return view('livewire.laravel-logs.index');
    }

    protected function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    protected function logLabel(): string
    {
        return 'Laravel log';
    }
}
