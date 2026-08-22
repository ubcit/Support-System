<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certification_runs', function (Blueprint $table) {
            $table->foreignId('benchmark_session_id')->nullable()->constrained('benchmark_sessions')->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->decimal('score', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certification_runs', function (Blueprint $table) {
            $table->dropForeign(['benchmark_session_id']);
            $table->dropForeign(['ai_model_id']);
            $table->dropColumn(['benchmark_session_id', 'ai_model_id', 'score']);
        });
    }
};
