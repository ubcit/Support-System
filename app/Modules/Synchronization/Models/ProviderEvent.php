<?php

namespace Modules\Synchronization\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderEvent extends Model
{
    protected $fillable = [
        'provider',
        'external_event_id',
        'event_type',
        'payload',
        'status',
        'error',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
