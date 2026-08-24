<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tasks\Services\NativeTaskService;

class PurgeTrashedTasks extends Command
{
    protected $signature = 'tasks:purge-trash {--days=30 : Soft-deleted tasks older than this many days are permanently removed}';

    protected $description = 'Permanently delete soft-deleted tasks that have been in Trash longer than the retention period';

    public function handle(NativeTaskService $taskService): int
    {
        $days = max(1, (int) $this->option('days'));
        $count = $taskService->purgeExpiredTrash($days);

        $this->info("Purged {$count} trashed task(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
