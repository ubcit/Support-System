<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_project', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('role', 100)->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->primary(['employee_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_project');
    }
};
