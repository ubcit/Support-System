<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

        if (! Schema::hasColumn('tags', 'workspace_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->after('id')
                    ->constrained('workspaces')->nullOnDelete();
            });
        }

        $this->dropNameUniqueIfExists();

        $defaultWorkspaceId = DB::table('workspaces')->orderBy('id')->value('id');

        foreach (DB::table('tags')->whereNull('workspace_id')->get() as $tag) {
            $workspaceId = DB::table('task_tag')
                ->join('tasks', 'tasks.id', '=', 'task_tag.task_id')
                ->where('task_tag.tag_id', $tag->id)
                ->whereNotNull('tasks.workspace_id')
                ->value('tasks.workspace_id') ?? $defaultWorkspaceId;

            DB::table('tags')->where('id', $tag->id)->update([
                'workspace_id' => $workspaceId,
            ]);
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'name']);
        });

        if (Schema::hasColumn('tags', 'workspace_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropConstrainedForeignId('workspace_id');
            });
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    private function dropNameUniqueIfExists(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: rebuild via temporary table is heavy; dropIndex by conventional name.
            Schema::table('tags', function (Blueprint $table) {
                $table->dropUnique(['name']);
            });

            return;
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
