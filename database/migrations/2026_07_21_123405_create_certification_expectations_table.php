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
        Schema::create('certification_expectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_test_id')->constrained('certification_tests')->cascadeOnDelete();
            $table->json('expected_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_expectations');
    }
};
