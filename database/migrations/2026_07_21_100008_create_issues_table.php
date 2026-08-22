<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 500);
            $table->text('description'); // The main issue description
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            
            // Workflow Engine Foreign Keys
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->foreignId('current_state_id')->nullable()->constrained('workflow_states')->nullOnDelete();

            $table->string('priority')->default('medium');
            $table->string('source')->default('manual'); // manual, whatsapp, email, api
            $table->text('ai_summary')->nullable();
            $table->json('ai_metadata')->nullable();
            
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('priority');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
