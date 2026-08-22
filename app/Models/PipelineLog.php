<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineLog extends Model
{
    protected $fillable = [
        'uuid',
        'status',
        'stage_timings',
        'stage_status',
        'input_payload',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'stage_timings' => 'array',
            'stage_status' => 'array',
            'input_payload' => 'array',
        ];
    }
}
