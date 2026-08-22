<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set email_notifications_enabled = true in metadata for all existing
        // employees that don't already have a preference stored. The
        // EmailNotificationService reads this flag from the metadata JSON
        // column, along with per-type preferences under notification_preferences.
        DB::table('employees')
            ->whereNull('metadata->email_notifications_enabled')
            ->update([
                'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.email_notifications_enabled', true)"),
            ]);
    }

    public function down(): void
    {
        DB::table('employees')
            ->whereNotNull('metadata->email_notifications_enabled')
            ->update([
                'metadata' => DB::raw("JSON_REMOVE(metadata, '$.email_notifications_enabled')"),
            ]);
    }
};
