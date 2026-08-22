<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // e.g. employee, project, customer, system
            $table->unsignedBigInteger('entity_id')->nullable(); // Can be null for system-wide stats
            $table->date('date');
            $table->json('metrics'); // Counters: tasks_completed, average_resolution_time, etc.
            $table->timestamps();

            // Only one snapshot per entity per day
            $table->unique(['entity_type', 'entity_id', 'date']);
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Overdue Task Rate"
            $table->string('entity_type'); // e.g. project, employee
            $table->json('calculation_rule'); // e.g. {"formula": "tasks_overdue / tasks_total"}
            $table->json('threshold'); // e.g. {">": 0.15}
            $table->json('alert_action'); // Rules Engine command to fire if breached
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('daily_snapshots');
    }
};
