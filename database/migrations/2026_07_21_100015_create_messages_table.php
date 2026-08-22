<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('channel');
            $table->string('direction');
            $table->string('sender_identifier');
            $table->string('sender_name')->nullable();
            $table->string('recipient_identifier')->nullable();
            $table->string('subject', 500)->nullable();
            $table->text('body')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('received');
            $table->json('processing_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('status');
            $table->index('sender_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
