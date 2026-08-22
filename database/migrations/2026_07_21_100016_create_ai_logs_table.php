<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 100);
            $table->string('model', 100);
            $table->string('action', 100);
            $table->text('input_text')->nullable();
            $table->text('output_text')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_cents', 10, 4)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->nullableMorphs('loggable');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
