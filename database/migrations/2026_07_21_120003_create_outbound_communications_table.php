<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel'); // e.g. whatsapp, email
            $table->string('locale')->default('en');
            $table->text('subject_template')->nullable();
            $table->text('body_template');
            $table->integer('version')->default(1);
            $table->timestamps();
            
            $table->unique(['name', 'channel', 'locale', 'version']);
        });

        Schema::create('outbound_communications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g. notification, customer_message
            $table->string('recipient_type'); // polymorphic
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel'); // whatsapp, email, push
            
            $table->foreignId('template_id')->nullable()->constrained('message_templates');
            
            // Raw rendered content if not using templates, or post-render cache
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            
            $table->string('priority')->default('normal'); // high, normal, low
            $table->string('status')->default('pending'); // pending, queued, sending, sent, delivered, read, failed, cancelled
            
            $table->string('provider')->nullable(); // e.g. twilio, whatsapp_cloud
            $table->string('provider_message_id')->nullable(); // Ext ID for read receipts
            
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_communications');
        Schema::dropIfExists('message_templates');
    }
};
