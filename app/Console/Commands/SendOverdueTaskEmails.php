<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Services\EmailNotificationService;

class SendOverdueTaskEmails extends Command
{
    protected $signature = 'notifications:overdue-tasks';

    protected $description = 'Send email reminders for overdue tasks to assigned employees';

    public function handle(EmailNotificationService $service): int
    {
        $count = $service->sendOverdueReminders();

        $this->info("Sent overdue task reminders to {$count} employees.");

        return self::SUCCESS;
    }
}
