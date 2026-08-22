<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_name'); // e.g. TaskStateChanged
            
            // Nested conditions JSON: e.g. {"operator":"AND","rules":[{"field":"task.priority","op":"==","val":"high"}]}
            $table->json('conditions'); 
            
            // Actions to dispatch if conditions pass: e.g. [{"action":"notify","target":"boss"}]
            $table->json('actions');
            
            $table->integer('priority')->default(0); // Higher runs first
            $table->boolean('stop_processing')->default(false); // If true, halt further rules on this event
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index('event_name');
            $table->index('priority');
        });

        Schema::create('rule_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('business_rules')->cascadeOnDelete();
            $table->string('event_name');
            
            // Snapshot of context passed to rule
            $table->json('context_snapshot')->nullable();
            
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            
            $table->boolean('is_success')->default(true);
            $table->text('error_message')->nullable();
            $table->boolean('is_simulation')->default(false); // True for Dry Runs

            $table->timestamps();
            
            $table->index('rule_id');
            $table->index('event_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_executions');
        Schema::dropIfExists('business_rules');
    }
};
