<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSchema extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'version', 'schema_json', 'is_active'];
    protected $casts = [
        'schema_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function prompts()
    {
        return $this->hasMany(AiPrompt::class);
    }
}
