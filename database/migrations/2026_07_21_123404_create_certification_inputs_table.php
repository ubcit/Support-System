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
        Schema::create('certification_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_test_id')->constrained('certification_tests')->cascadeOnDelete();
            $table->string('customer_phone')->nullable();
            $table->string('project_name')->nullable();
            $table->text('boss_notes')->nullable();
            $table->json('messages')->nullable();
            $table->json('attachments')->nullable();
            $table->string('provider')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_inputs');
    }
};
