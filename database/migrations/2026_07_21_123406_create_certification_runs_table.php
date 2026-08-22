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
        Schema::create('certification_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_test_id')->constrained('certification_tests')->cascadeOnDelete();
            $table->foreignId('pipeline_log_id')->nullable()->constrained('pipeline_logs')->nullOnDelete();
            $table->integer('duration_ms')->default(0);
            $table->string('ai_provider')->nullable();
            $table->string('sync_provider')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_runs');
    }
};
