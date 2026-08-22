<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_sessions') && ! Schema::hasColumn('conversation_sessions', 'customer_locale')) {
            Schema::table('conversation_sessions', function (Blueprint $table) {
                $table->string('customer_locale', 8)->nullable()->after('status');
            });
        }

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'preferred_locale')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('preferred_locale', 8)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_sessions') && Schema::hasColumn('conversation_sessions', 'customer_locale')) {
            Schema::table('conversation_sessions', function (Blueprint $table) {
                $table->dropColumn('customer_locale');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'preferred_locale')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('preferred_locale');
            });
        }
    }
};
