<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `workspace_id` was added to projects/customers/employees back in
 * 2026_07_21_160002_add_workspace_id_to_root_entities.php, but several
 * existing creation paths (InitialPlatformSeeder, CustomerCRM Livewire page,
 * the Projects/Customers API services) never set it, leaving real rows with
 * a NULL workspace_id. Enabling the BelongsToWorkspace global scope without
 * backfilling those rows first would make them invisible to every regular
 * (non ceo/boss) user. This is safe because, today, only one Workspace
 * exists in practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        $workspaceId = DB::table('workspaces')->orderBy('id')->value('id');

        if (! $workspaceId) {
            return;
        }

        DB::table('projects')->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
        DB::table('customers')->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
        DB::table('employees')->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
    }

    public function down(): void
    {
        // Intentionally irreversible: we can't know which of these rows were
        // NULL before this migration ran.
    }
};
