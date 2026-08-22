<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Services\EmailNotificationService;

class SendProjectDeadlineEmails extends Command
{
    protected $signature = 'notifications:project-deadlines';

    protected $description = 'Send email alerts for projects with approaching deadlines';

    public function handle(EmailNotificationService $service): int
    {
        $count = $service->sendProjectDeadlineAlerts();

        $this->info("Sent project deadline alerts to {$count} employees.");

        return self::SUCCESS;
    }
}
