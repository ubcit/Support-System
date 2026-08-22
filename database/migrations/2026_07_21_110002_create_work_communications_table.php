<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_communications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // What work item is this attached to? (Issue or Task)
            $table->morphs('communicable');
            
            // Threading
            $table->foreignId('parent_id')->nullable()->constrained('work_communications')->nullOnDelete();
            
            // Who wrote it? (Employee, Customer, User, System, AI, Webhook)
            $table->nullableMorphs('author');
            
            $table->string('type'); // comment, ai_note, system_event, status_change, etc.
            $table->string('visibility')->default('internal'); // internal, customer_visible, private, system
            $table->string('status')->default('published'); // published, draft, edited, deleted
            
            $table->longText('content')->nullable(); // Rich Text / Markdown
            $table->json('metadata')->nullable(); // For mentions, state transitions, raw webhook payloads
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('type');
            $table->index('visibility');
            $table->index(['communicable_type', 'communicable_id', 'created_at'], 'work_comms_stream_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_communications');
    }
};
