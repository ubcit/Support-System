<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Services\EmailNotificationService;

class SendDailyDigestEmails extends Command
{
    protected $signature = 'notifications:daily-digest';

    protected $description = 'Send daily digest summary emails to all employees';

    public function handle(EmailNotificationService $service): int
    {
        $this->info('Running daily digest (timezone '.config('app.timezone').', now '.now()->toDateTimeString().')...');

        $stats = $service->sendDailyDigests();

        Log::info('notifications:daily-digest finished', $stats);

        $this->info("Daily digest: sent={$stats['sent']} failed={$stats['failed']} skipped_pref={$stats['skipped_pref']} skipped_empty={$stats['skipped_empty']} candidates={$stats['candidates']}");

        if ($stats['failed'] > 0) {
            $this->warn('Some digests failed — check notification_logs metadata.error and storage/logs/laravel.log');

            return self::FAILURE;
        }

        if ($stats['sent'] === 0 && $stats['candidates'] > 0) {
            $this->comment('No digests sent (everyone empty or opted out). That is OK if there is nothing to report.');
        }

        return self::SUCCESS;
    }
}
