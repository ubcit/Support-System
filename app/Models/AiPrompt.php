<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'version', 'purpose', 'ai_schema_id', 
        'system_prompt', 'user_prompt_template', 
        'temperature', 'provider_overrides', 'is_active'
    ];

    protected $casts = [
        'provider_overrides' => 'array',
        'is_active' => 'boolean',
        'temperature' => 'float',
    ];

    public function schema()
    {
        return $this->belongsTo(AiSchema::class, 'ai_schema_id');
    }
}
