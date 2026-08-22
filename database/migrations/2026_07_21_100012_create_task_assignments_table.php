<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, accepted, rejected, started, blocked, completed
            $table->string('role')->default('assignee'); // assignee, reviewer, qa
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();

            // Employee can have one role per task at a time
            $table->unique(['task_id', 'employee_id', 'role']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
