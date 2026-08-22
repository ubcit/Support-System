<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_models')
            ->where('provider', 'gemini')
            ->whereIn('api_model_id', [
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
            ])
            ->update(['api_model_id' => 'gemini-3.5-flash-lite']);
    }

    public function down(): void
    {
        // Retired model IDs cannot be restored.
    }
};
