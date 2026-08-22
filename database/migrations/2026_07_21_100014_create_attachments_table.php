<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('attachable');
            $table->string('original_name', 500);
            $table->string('stored_path', 1000)->nullable();
            $table->string('disk', 50)->default('local');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('type')->default('other');
            $table->string('sha256')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_media_id')->nullable();
            $table->text('provider_url')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('ai_transcript')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
