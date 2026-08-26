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
        $this->line('- Task/issue emails send in-process via SendNotificationEmailJob::dispatchNotify() (no queue worker).');
        $this->line('- Digests/due-soon/overdue use the same EmailNotificationService::sendNow path.');
        $this->line('- Actor/creator is skipped; self-assign does not email you.');
        $this->line('- No email on generic status/field edits (To Do → In Progress). Only assign/create/complete/review/mention/issues.');
        $this->line('- Mail goes to the employee.email of the recipient — check notification_logs if inbox is empty.');
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
            $recent = DB::table('notification_logs')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'recipient', 'subject', 'body', 'status', 'created_at']);

            if ($recent->isNotEmpty()) {
                $this->newLine();
                $this->info('Recent notification_logs');
                $this->table(
                    ['id', 'recipient', 'subject', 'body', 'status', 'created_at'],
                    $recent->map(fn ($row) => [
                        (string) $row->id,
                        (string) $row->recipient,
                        (string) $row->subject,
                        (string) $row->body,
                        (string) $row->status,
                        (string) $row->created_at,
                    ])->all()
                );
            } else {
                $this->line('notification_logs: (empty)');
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
        $sample = $this->option('sample');

        if ((! is_string($to) || $to === '') && (! is_string($sample) || $sample === '')) {
            $this->newLine();
            $this->comment('Re-run with --send=you@example.com to perform a live SMTP test.');
            $this->comment('Add --sample=task_assigned to also send a TaskAssignedMail via EmailNotificationService.');

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
                $this->info('Sample task_assigned completed. Check inbox and notification_logs.');
            } catch (Throwable $e) {
                $this->error(get_class($e).': '.$e->getMessage());
                $this->printSmtpHints($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->comment('SMTP path OK. Assign a task to a different employee (real mailbox) to verify event emails.');

        return self::SUCCESS;
    }

    protected function sendTaskAssignedSample(string $to): void
    {
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

        $logged = NotificationLog::query()
            ->where('recipient', $to)
            ->where('body', 'task_assigned')
            ->orderByDesc('id')
            ->exists();

        if (! $logged) {
            // Fallback if service skipped for any reason — still exercise the mailable.
            Mail::to($to)->sendNow(new TaskAssignedMail($probe, $task));
            $this->warn('Service did not log task_assigned; sent TaskAssignedMail directly as fallback.');
        }
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
