<?php

namespace App\Livewire\Concerns;

trait ReadsStorageLogFile
{
    public const MAX_BYTES = 512_000;

    public string $content = '';

    public string $path = '';

    public bool $exists = false;

    public bool $readable = false;

    public int $fileSize = 0;

    public bool $truncated = false;

    abstract protected function logPath(): string;

    abstract protected function logLabel(): string;

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

        if (! is_writable($path) && ! is_writable(dirname($path))) {
            session()->flash('error', 'Log file is not writable (permission denied). Fix ownership: sudo chown -R www-data:www-data storage/logs && sudo chmod -R 775 storage/logs');
            $this->loadLog();

            return;
        }

        if (file_put_contents($path, '') === false) {
            session()->flash('error', 'Could not clear the log file (permission denied?).');
            $this->loadLog();

            return;
        }

        session()->flash('success', $this->logLabel().' cleared.');
        $this->loadLog();
    }

    protected function loadLog(): void
    {
        $path = $this->logPath();
        $this->path = $path;
        $this->exists = is_file($path);
        $this->readable = $this->exists && is_readable($path);
        $this->truncated = false;
        $this->fileSize = 0;
        $this->content = '';

        if (! $this->exists) {
            $this->content = 'Log file not found at '.$path.'. Supervisor creates it when the queue worker starts (ensure storage/logs is writable by www-data).';

            return;
        }

        if (! $this->readable) {
            $this->content = "Log file exists but is not readable (permission denied).\n\n"
                ."On the server run:\n"
                ."  sudo chown -R www-data:www-data storage bootstrap/cache\n"
                ."  sudo chmod -R 775 storage bootstrap/cache\n"
                ."  sudo touch storage/logs/worker.log storage/logs/shadow-worker.log storage/logs/laravel.log\n"
                ."  sudo chown www-data:www-data storage/logs/*.log\n"
                .'  sudo supervisorctl restart all';

            return;
        }

        $this->fileSize = (int) filesize($path);

        if ($this->fileSize === 0) {
            $this->content = '(empty)';

            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->content = 'Unable to open log file (permission denied?).';

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
}
