<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('role', 100);
            $table->string('department', 100)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('max_workload')->default(10);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('role');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
