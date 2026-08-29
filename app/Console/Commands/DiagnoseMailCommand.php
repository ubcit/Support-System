<?php

namespace App\Console\Commands;

use App\Mail\TaskAssignedMail;
use App\Support\EnsureTlsCaBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Tasks\Models\Task;
use RuntimeException;
use Throwable;

class DiagnoseMailCommand extends Command
{
    protected $signature = 'mail:diagnose
                            {--send= : Optional recipient address for a live SMTP smoke test}
                            {--sample= : Optional sample mailable (task_assigned) sent to --send address}';

    protected $description = 'Diagnose mailer/queue config and optionally send an SMTP smoke-test email';

    public function handle(): int
    {
        $caBundle = EnsureTlsCaBundle::apply();

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
                ['openssl.cafile', (string) (ini_get('openssl.cafile') ?: '(empty)')],
                ['curl.cainfo', (string) (ini_get('curl.cainfo') ?: '(empty)')],
                ['CA bundle resolved', $caBundle ?: '(none — OpenSSL may still use /etc/ssl/certs)'],
            ]
        );

        if ($caBundle === null) {
            $this->warn('No explicit CA file set. If SMTP fails with certificate verify failed, run:');
            $this->comment('  sudo apt install -y ca-certificates');
            $this->comment('  # then in .env: MAIL_CAFILE=/etc/ssl/certs/ca-certificates.crt');
            $this->comment('  php artisan config:cache && php artisan queue:restart');
        } else {
            $this->info("CA bundle OK: {$caBundle}");
        }

        if (config('mail.default') !== 'smtp') {
            $this->error('MAIL_MAILER is not smtp. Emails will not leave the server (log/array).');
        }

        $this->newLine();
        $this->info('Notification email rules');
        $this->line('- Event emails call EmailNotificationService directly (same sendNow path as digests; no queue re-find).');
        $this->line('- Digests/due-soon/overdue use the same EmailNotificationService::sendNow path.');
        $this->line('- Create/assign/delete: actor is also notified (in-app + email), including self-assign.');
        $this->line('- Events: task_assigned, task_created, task_completed, task_deleted, review_*, mentions, issues, project member.');
        $this->line('- No email on generic status/field edits (To Do → In Progress).');
        $this->line('- Check notification_logs status: sent / failed / skipped — and recipient (demo @thespace.app will not reach Gmail).');
        $this->line('- Failed rows store the exception in metadata.error (also in storage/logs/laravel.log).');
        $this->comment('Supervisor is still needed for WhatsApp, rules evaluation, and other queued jobs — not for notification SMTP.');

        $this->newLine();
        $this->info('Queue health');

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->line("pending jobs: {$pending}");
            $this->line("failed jobs: {$failed}");
            if ($pending > 0) {
                $this->warn('Jobs are waiting — check Supervisor workers.');
            }
            if ($failed > 0) {
                $this->warn('Old failures (often prior SSL errors). Inspect: php artisan queue:failed');
                $this->comment('Clear them after SMTP works: php artisan queue:flush');

                $samples = DB::table('failed_jobs')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get(['id', 'failed_at', 'exception']);

                foreach ($samples as $sample) {
                    $snippet = str_replace("\n", ' ', (string) $sample->exception);
                    $this->line("  #{$sample->id} @{$sample->failed_at}: ".mb_substr($snippet, 0, 160).'…');
                }
            }
        } catch (Throwable $e) {
            $this->warn('Could not read jobs tables: '.$e->getMessage());
        }

        try {
            $counts = DB::table('notification_logs')
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');
            if ($counts->isNotEmpty()) {
                $this->line('notification_logs by status: '.$counts->map(fn ($c, $s) => "{$s}={$c}")->implode(' '));
            }

            $this->printRecentNotificationLogs('Recent notification_logs');
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
        $sample = $this->option('sample');

        if ((! is_string($to) || $to === '') && (! is_string($sample) || $sample === '')) {
            $this->newLine();
            $this->comment('Re-run with --send=you@example.com to perform a live SMTP test.');
            $this->comment('Add --sample=task_assigned to also send a TaskAssignedMail via EmailNotificationService.');
            $this->comment('To inspect failed rows: metadata.error on notification_logs, or grep "Failed to send" storage/logs/laravel.log');

            return self::SUCCESS;
        }

        if (is_string($sample) && $sample !== '' && (! is_string($to) || $to === '')) {
            $this->error('--sample requires --send=address');

            return self::FAILURE;
        }

        if (is_string($to) && $to !== '') {
            $this->newLine();
            $this->info("Sending smoke test to {$to}...");

            try {
                Mail::raw('SMTP diagnose OK.', function ($message) use ($to) {
                    $message->to($to)->subject('Support System SMTP diagnose');
                });

                $this->info('Smoke test completed without exception (Mail::raw is synchronous).');
            } catch (Throwable $e) {
                $this->error(get_class($e).': '.$e->getMessage());
                $this->printSmtpHints($e->getMessage());

                return self::FAILURE;
            }
        }

        if (is_string($sample) && $sample !== '') {
            if ($sample !== 'task_assigned') {
                $this->error("Unknown --sample={$sample}. Supported: task_assigned");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info("Sending sample TaskAssignedMail to {$to} via EmailNotificationService...");

            try {
                $this->sendTaskAssignedSample($to);
                $this->info('Sample task_assigned completed with status=sent. Check inbox.');
            } catch (Throwable $e) {
                $this->error(get_class($e).': '.$e->getMessage());
                $this->printSmtpHints($e->getMessage());
                $this->printRecentNotificationLogs('notification_logs after sample (failed)');

                return self::FAILURE;
            }
        }

        $this->printRecentNotificationLogs('notification_logs after send');
        $this->comment('SMTP path OK. Assign a task to a different employee (real mailbox) to verify event emails.');

        return self::SUCCESS;
    }

    /**
     * @throws RuntimeException when the sample did not log status=sent
     */
    protected function sendTaskAssignedSample(string $to): void
    {
        $beforeId = (int) (NotificationLog::query()->max('id') ?? 0);

        $task = Task::query()->with('project')->latest('id')->first();

        if (! $task) {
            $task = Task::make([
                'title' => 'SMTP diagnose sample task',
                'priority' => 'medium',
                'summary' => 'Generated by mail:diagnose --sample=task_assigned',
            ]);
        }

        $probe = Employee::make([
            'name' => 'Mail diagnose probe',
            'email' => $to,
            'metadata' => ['email_notifications_enabled' => true],
        ]);

        // Prefer the service path (shouldNotify + sendNow + notification_logs).
        // Unsaved probe has null id — morph columns are nullable.
        app(EmailNotificationService::class)->sendTaskAssigned($task, $probe);

        $log = NotificationLog::query()
            ->where('recipient', $to)
            ->where('body', 'task_assigned')
            ->where('id', '>', $beforeId)
            ->orderByDesc('id')
            ->first();

        if (! $log) {
            // Fallback if service skipped for any reason — still exercise the mailable.
            try {
                Mail::to($to)->sendNow(new TaskAssignedMail($probe, $task));
                NotificationLog::create([
                    'channel' => 'email',
                    'recipient' => $to,
                    'subject' => 'Task assigned: '.$task->title,
                    'body' => 'task_assigned',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['type' => 'task_assigned', 'source' => 'diagnose_fallback'],
                ]);
                $this->warn('Service did not log task_assigned; sent TaskAssignedMail directly as fallback.');
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Sample task_assigned was not logged by EmailNotificationService and fallback send failed: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            return;
        }

        if ($log->status !== 'sent') {
            $error = is_array($log->metadata) ? (string) ($log->metadata['error'] ?? 'unknown') : 'unknown';

            throw new RuntimeException(
                "Sample task_assigned logged as {$log->status} (notification_logs.id={$log->id}): {$error}"
            );
        }
    }

    protected function printRecentNotificationLogs(string $heading): void
    {
        $recent = DB::table('notification_logs')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'recipient', 'subject', 'body', 'status', 'metadata', 'created_at']);

        if ($recent->isEmpty()) {
            $this->line('notification_logs: (empty)');

            return;
        }

        $this->newLine();
        $this->info($heading);
        $this->table(
            ['id', 'recipient', 'subject', 'body', 'status', 'error', 'created_at'],
            $recent->map(fn ($row) => [
                (string) $row->id,
                (string) $row->recipient,
                mb_substr((string) $row->subject, 0, 40),
                (string) $row->body,
                (string) $row->status,
                $this->formatLogError($row->metadata),
                (string) $row->created_at,
            ])->all()
        );
    }

    protected function formatLogError(mixed $metadata): string
    {
        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($metadata)) {
            return '-';
        }

        $error = $metadata['error'] ?? null;
        if (! is_string($error) || $error === '') {
            return '-';
        }

        $oneLine = preg_replace('/\s+/', ' ', $error) ?? $error;

        return mb_substr($oneLine, 0, 80);
    }

    protected function printSmtpHints(string $message): void
    {
        if (str_contains($message, 'certificate verify failed')) {
            $this->warn('Fix: sudo apt install ca-certificates && MAIL_CAFILE=/etc/ssl/certs/ca-certificates.crt');
        }
        if (str_contains($message, 'Connection could not be established')) {
            $this->warn('Try MAIL_PORT=465 and MAIL_SCHEME=smtps, and allow outbound TCP 465/587.');
        }
    }
}
