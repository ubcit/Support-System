<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\MultiTenancy\Models\Workspace;

class BackfillWorkspaceId extends Command
{
    protected $signature = 'workspace:backfill';

    protected $description = 'Assign existing rows with null workspace_id to the first workspace';

    public function handle(): int
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->error('No workspace found. Run the onboarding wizard first.');
            return self::FAILURE;
        }

        $tables = [
            'tasks', 'issues', 'conversations', 'conversation_sessions',
            'messages', 'attachments', 'notifications', 'workflows', 'workflow_states',
        ];

        foreach ($tables as $table) {
            if (! \Schema::hasColumn($table, 'workspace_id')) {
                $this->warn("Skipped {$table} — no workspace_id column.");
                continue;
            }

            $count = DB::table($table)->whereNull('workspace_id')->update([
                'workspace_id' => $workspace->id,
            ]);

            $this->info("Backfilled {$count} rows in {$table}.");
        }

        return self::SUCCESS;
    }
}
