<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'tasks',
        'issues',
        'conversations',
        'conversation_sessions',
        'messages',
        'attachments',
        'notifications',
        'workflows',
        'workflow_states',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'workspace_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('workspace_id')->nullable()->after('id')
                      ->constrained('workspaces')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'workspace_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('workspace_id');
                });
            }
        }
    }
};
