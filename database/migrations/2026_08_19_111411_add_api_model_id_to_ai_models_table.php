<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('api_model_id')->nullable()->after('name');
        });

        // Seed existing models with correct API identifiers
        DB::table('ai_models')->where('name', 'Gemini 1.5 Flash')->update(['api_model_id' => 'gemini-1.5-flash']);
        DB::table('ai_models')->where('name', 'GPT-4o')->update(['api_model_id' => 'gpt-4o']);
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('api_model_id');
        });
    }
};
