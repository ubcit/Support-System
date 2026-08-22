<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Services\EmailNotificationService;

class SendDueSoonEmails extends Command
{
    protected $signature = 'notifications:due-soon';

    protected $description = 'Send email reminders for tasks due within 24 hours';

    public function handle(EmailNotificationService $service): int
    {
        $count = $service->sendDueSoonReminders();

        $this->info("Sent due-soon reminders to {$count} employees.");

        return self::SUCCESS;
    }
}
