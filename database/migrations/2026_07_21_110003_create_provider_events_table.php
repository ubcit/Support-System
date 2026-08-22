<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g. clickup, jira
            $table->string('external_event_id'); // e.g. webhook_id
            $table->string('event_type'); // e.g. taskStatusUpdated
            $table->json('payload');
            
            $table->string('status')->default('pending'); // pending, processed, failed, ignored
            $table->text('error')->nullable();
            
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
