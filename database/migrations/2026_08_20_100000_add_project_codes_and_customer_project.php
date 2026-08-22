<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('code', 16)->nullable()->after('uuid');
        });

        $this->backfillProjectCodes();

        Schema::table('projects', function (Blueprint $table) {
            $table->unique(['workspace_id', 'code']);
        });

        Schema::create('customer_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'project_id']);
        });

        $this->backfillCustomerProjectPivot();

        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('customer_project');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'code']);
            $table->dropColumn('code');
        });
    }

    protected function backfillProjectCodes(): void
    {
        $projects = DB::table('projects')->whereNull('code')->orderBy('id')->get(['id', 'workspace_id']);
        $used = [];

        foreach ($projects as $project) {
            $workspaceId = $project->workspace_id ?? 0;
            do {
                $code = $this->generateCode();
            } while (isset($used[$workspaceId][$code]));

            $used[$workspaceId][$code] = true;

            DB::table('projects')->where('id', $project->id)->update(['code' => $code]);
        }
    }

    protected function backfillCustomerProjectPivot(): void
    {
        $rows = DB::table('projects')
            ->whereNotNull('customer_id')
            ->get(['id', 'customer_id', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            DB::table('customer_project')->insertOrIgnore([
                'customer_id' => $row->customer_id,
                'project_id' => $row->id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    protected function generateCode(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
};
