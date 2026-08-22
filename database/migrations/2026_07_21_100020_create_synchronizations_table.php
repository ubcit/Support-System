<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synchronizations', function (Blueprint $table) {
            $table->id();
            $table->morphs('syncable');
            $table->string('provider'); // clickup, native, etc.
            $table->string('provider_object_id')->nullable();
            $table->string('status')->default('pending'); // pending, synced, failed
            $table->timestamp('last_attempt')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('payload_hash')->nullable(); // sha256 to avoid redundant syncs
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['syncable_type', 'syncable_id', 'provider'], 'syncable_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synchronizations');
    }
};
