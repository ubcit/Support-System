<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name'); // e.g. TaskCreated
            $table->string('aggregate_type'); // e.g. Task
            $table->unsignedBigInteger('aggregate_id');
            
            $table->uuid('correlation_id')->nullable();
            $table->uuid('causation_id')->nullable(); // ID of the event that caused this one
            
            $table->json('payload');
            $table->integer('version')->default(1);
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('correlation_id');
            $table->index('event_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
