<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('daily_ai_cost_limit', 10, 4)->nullable()->after('is_active');
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('ai_prompt_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->after('customer_id')->constrained('conversations')->nullOnDelete();
            $table->foreignId('conversation_session_id')->nullable()->after('conversation_id')->constrained('conversation_sessions')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('conversation_session_id')->constrained('projects')->nullOnDelete();
            $table->string('source', 40)->nullable()->after('project_id');
            $table->index(['customer_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
        });

        $sessions = DB::table('conversation_sessions')
            ->join('conversations', 'conversations.id', '=', 'conversation_sessions.conversation_id')
            ->whereNotNull('conversation_sessions.request_log_id')
            ->select(
                'conversation_sessions.id as session_id',
                'conversation_sessions.request_log_id',
                'conversations.id as conversation_id',
                'conversations.customer_id'
            )
            ->get();

        foreach ($sessions as $row) {
            DB::table('ai_request_logs')
                ->where('id', $row->request_log_id)
                ->update([
                    'customer_id' => $row->customer_id,
                    'conversation_id' => $row->conversation_id,
                    'conversation_session_id' => $row->session_id,
                    'source' => 'conversation',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('conversation_session_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('source');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('daily_ai_cost_limit');
        });
    }
};
