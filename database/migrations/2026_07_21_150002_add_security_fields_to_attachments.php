<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('disk'); // pending, scanning, quarantined, ready, duplicate
            $table->string('hash')->nullable()->after('status'); // SHA-256 for deduplication
            $table->boolean('is_duplicate')->default(false)->after('hash');
            $table->json('security_metadata')->nullable()->after('is_duplicate'); // For antivirus scan results etc.
            
            $table->index('hash');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['status', 'hash', 'is_duplicate', 'security_metadata']);
        });
    }
};
