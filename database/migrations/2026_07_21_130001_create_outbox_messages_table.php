<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // Fully qualified event class name
            $table->json('payload'); // Serialized event data
            $table->string('status')->default('pending'); // pending, processing, published, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            
            $table->timestamps();
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
