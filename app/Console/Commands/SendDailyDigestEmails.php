<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Services\EmailNotificationService;

class SendDailyDigestEmails extends Command
{
    protected $signature = 'notifications:daily-digest';

    protected $description = 'Send daily digest summary emails to all employees';

    public function handle(EmailNotificationService $service): int
    {
        $count = $service->sendDailyDigests();

        $this->info("Sent daily digest emails to {$count} employees.");

        return self::SUCCESS;
    }
}
