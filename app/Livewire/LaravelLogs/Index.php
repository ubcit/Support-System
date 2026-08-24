<?php

namespace App\Livewire\LaravelLogs;

use Livewire\Component;

class Index extends Component
{
    public const MAX_BYTES = 512_000;

    public string $content = '';

    public string $path = '';

    public bool $exists = false;

    public int $fileSize = 0;

    public bool $truncated = false;

    public function mount(): void
    {
        $this->loadLog();
    }

    public function refresh(): void
    {
        $this->loadLog();
    }

    public function clear(): void
    {
        $path = $this->logPath();

        if (! is_file($path)) {
            session()->flash('error', 'Log file does not exist.');
            $this->loadLog();

            return;
        }

        if (file_put_contents($path, '') === false) {
            session()->flash('error', 'Could not clear the log file.');
            $this->loadLog();

            return;
        }

        session()->flash('success', 'Laravel log cleared.');
        $this->loadLog();
    }

    public function render()
    {
        return view('livewire.laravel-logs.index');
    }

    protected function loadLog(): void
    {
        $path = $this->logPath();
        $this->path = $path;
        $this->exists = is_file($path);
        $this->truncated = false;
        $this->fileSize = 0;
        $this->content = '';

        if (! $this->exists) {
            $this->content = 'Log file not found. It will be created when the application writes its first log entry.';

            return;
        }

        $this->fileSize = (int) filesize($path);

        if ($this->fileSize === 0) {
            $this->content = '(empty)';

            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->content = 'Unable to read log file.';

            return;
        }

        if ($this->fileSize > self::MAX_BYTES) {
            $this->truncated = true;
            fseek($handle, -self::MAX_BYTES, SEEK_END);
        }

        $raw = stream_get_contents($handle) ?: '';
        fclose($handle);

        if ($this->truncated) {
            $newline = strpos($raw, "\n");
            if ($newline !== false) {
                $raw = substr($raw, $newline + 1);
            }
            $this->content = '… (showing last ~'.number_format(self::MAX_BYTES)." bytes)\n\n".$raw;

            return;
        }

        $this->content = $raw;
    }

    protected function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }
}
