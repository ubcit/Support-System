<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_states', function (Blueprint $table) {
            $table->unsignedInteger('wip_limit')->default(0)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_states', function (Blueprint $table) {
            $table->dropColumn('wip_limit');
        });
    }
};
