<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenchmarkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'ai_prompt_id', 'ai_schema_id', 'status', 'created_by'
    ];

    public function prompt() { return $this->belongsTo(AiPrompt::class, 'ai_prompt_id'); }
    public function schema() { return $this->belongsTo(AiSchema::class, 'ai_schema_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function runs() { return $this->hasMany(CertificationRun::class); }
}
