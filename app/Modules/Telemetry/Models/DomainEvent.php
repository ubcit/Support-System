<?php

namespace Modules\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

class DomainEvent extends Model
{
    public $timestamps = false; // Only created_at is used, managed by DB default

    protected $fillable = [
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'correlation_id',
        'causation_id',
        'payload',
        'version',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
