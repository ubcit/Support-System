<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_stakeholders', function (Blueprint $table) {
            $table->string('approval_status')->nullable()->after('role');
            $table->text('approval_note')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_note');
        });
    }

    public function down(): void
    {
        Schema::table('task_stakeholders', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_note', 'approved_at']);
        });
    }
};
