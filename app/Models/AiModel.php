<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'api_model_id', 'provider', 'context_length', 
        'supports_vision', 'supports_audio', 
        'supports_json', 'supports_tools', 'is_active', 'is_shadow_mode'
    ];

    /**
     * The identifier sent to the provider API (e.g. "gpt-4o", "gemini-1.5-flash").
     */
    public function apiModelId(): string
    {
        return $this->api_model_id ?? $this->name;
    }

    protected $casts = [
        'supports_vision' => 'boolean',
        'supports_audio' => 'boolean',
        'supports_json' => 'boolean',
        'supports_tools' => 'boolean',
        'is_active' => 'boolean',
    ];
}
