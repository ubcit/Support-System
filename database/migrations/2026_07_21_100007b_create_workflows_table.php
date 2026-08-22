<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity_type'); // 'task', 'issue'
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('name'); // e.g. "Pending", "In Progress", "Code Review"
            $table->string('type'); // 'initial', 'active', 'completed', 'cancelled'
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('from_state_id')->constrained('workflow_states')->cascadeOnDelete();
            $table->foreignId('to_state_id')->constrained('workflow_states')->cascadeOnDelete();
            $table->json('roles_allowed')->nullable(); // Which assignment roles can make this transition
            $table->timestamps();
            
            $table->unique(['workflow_id', 'from_state_id', 'to_state_id'], 'workflow_transition_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('workflows');
    }
};
