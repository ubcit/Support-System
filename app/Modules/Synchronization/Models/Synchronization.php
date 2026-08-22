<?php

namespace Modules\Synchronization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Synchronization extends Model
{
    protected $table = 'synchronizations';

    protected $fillable = [
        'syncable_type',
        'syncable_id',
        'provider',
        'provider_object_id',
        'status',
        'last_attempt',
        'attempts',
        'last_error',
        'payload_hash',
    ];

    protected $casts = [
        'last_attempt' => 'datetime',
        'attempts' => 'integer',
    ];

    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }
}
