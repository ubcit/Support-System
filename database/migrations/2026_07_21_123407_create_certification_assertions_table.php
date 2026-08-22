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
        Schema::create('certification_assertions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_run_id')->constrained('certification_runs')->cascadeOnDelete();
            $table->string('key');
            $table->text('expected_value')->nullable();
            $table->text('actual_value')->nullable();
            $table->boolean('passed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_assertions');
    }
};
