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
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version')->default('v1');
            $table->string('purpose')->nullable();
            $table->foreignId('ai_schema_id')->nullable()->constrained('ai_schemas')->nullOnDelete();
            $table->text('system_prompt')->nullable();
            $table->text('user_prompt_template')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.00);
            $table->json('provider_overrides')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
