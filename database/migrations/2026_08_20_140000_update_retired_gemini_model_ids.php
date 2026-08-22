<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacement = 'gemini-3.5-flash-lite';

        DB::table('ai_models')
            ->where('provider', 'gemini')
            ->whereIn('api_model_id', [
                'gemini-1.5-flash',
                'gemini-1.5-flash-latest',
                'gemini-2.0-flash',
                'gemini-2.0-flash-lite',
                'gemini-2.0-flash-001',
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
            ])
            ->update(['api_model_id' => $replacement]);

        DB::table('ai_models')
            ->where('provider', 'gemini')
            ->whereNull('api_model_id')
            ->whereIn('name', ['Gemini Flash', 'Gemini 1.5 Flash'])
            ->update(['api_model_id' => $replacement]);
    }

    public function down(): void
    {
        // Retired model IDs cannot be restored.
    }
};
