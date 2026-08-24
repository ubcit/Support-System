<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DiagnoseMailCommand extends Command
{
    protected $signature = 'mail:diagnose
                            {--send= : Optional recipient address for a live SMTP smoke test}';

    protected $description = 'Diagnose mailer/queue config and optionally send an SMTP smoke-test email';

    public function handle(): int
    {
        $this->info('Mail / queue runtime config');
        $this->table(
            ['Key', 'Value'],
            [
                ['mail.default', (string) config('mail.default')],
                ['smtp.host', (string) config('mail.mailers.smtp.host')],
                ['smtp.port', (string) config('mail.mailers.smtp.port')],
                ['smtp.scheme', (string) (config('mail.mailers.smtp.scheme') ?? 'null')],
                ['smtp.username', (string) config('mail.mailers.smtp.username')],
                ['smtp.password', config('mail.mailers.smtp.password') ? '(set)' : '(empty)'],
                ['mail.from', (string) config('mail.from.address')],
                ['queue.default', (string) config('queue.default')],
                ['openssl.cafile', (string) (ini_get('openssl.cafile') ?: '(empty — TLS verify may fail)')],
            ]
        );

        if (config('mail.default') !== 'smtp') {
            $this->error('MAIL_MAILER is not smtp. Emails will not leave the server (log/array).');
        }

        $this->newLine();
        $this->info('Queue health');

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->line("pending jobs: {$pending}");
            $this->line("failed jobs: {$failed}");
            if ($pending > 0) {
                $this->warn('Jobs are waiting — start/restart queue workers (Supervisor: queue:work).');
            }
            if ($failed > 0) {
                $this->warn('Run: php artisan queue:failed');
            }
        } catch (Throwable $e) {
            $this->warn('Could not read jobs tables: '.$e->getMessage());
        }

        try {
            $recent = DB::table('notification_logs')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'type', 'status', 'created_at']);

            if ($recent->isNotEmpty()) {
                $this->newLine();
                $this->info('Recent notification_logs');
                $this->table(
                    ['id', 'type', 'status', 'created_at'],
                    $recent->map(fn ($row) => [(string) $row->id, (string) $row->type, (string) $row->status, (string) $row->created_at])->all()
                );
            }
        } catch (Throwable $e) {
            $this->warn('notification_logs unavailable: '.$e->getMessage());
        }

        $supervisor = trim((string) shell_exec('command -v supervisorctl >/dev/null && sudo -n supervisorctl status 2>/dev/null || supervisorctl status 2>/dev/null'));
        $this->newLine();
        $this->info('Supervisor');
        if ($supervisor !== '') {
            $this->line($supervisor);
        } else {
            $this->warn('supervisorctl not available or not running from this shell.');
        }

        $to = $this->option('send');
        if (! is_string($to) || $to === '') {
            $this->newLine();
            $this->comment('Re-run with --send=you@example.com to perform a live SMTP test.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Sending smoke test to {$to}...");

        try {
            Mail::raw('SMTP diagnose OK.', function ($message) use ($to) {
                $message->to($to)->subject('Support System SMTP diagnose');
            });

            $this->info('Smoke test completed without exception (Mail::raw is synchronous).');
        } catch (Throwable $e) {
            $this->error(get_class($e).': '.$e->getMessage());

            if (str_contains($e->getMessage(), 'certificate verify failed')) {
                $this->warn('Fix: set openssl.cafile / install ca-certificates, then retry.');
            }
            if (str_contains($e->getMessage(), 'Connection could not be established')) {
                $this->warn('Try MAIL_PORT=465 and MAIL_SCHEME=smtps, and allow outbound TCP 465/587.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
