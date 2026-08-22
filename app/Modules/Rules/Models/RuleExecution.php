<?php

namespace Modules\Rules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleExecution extends Model
{
    protected $fillable = [
        'rule_id',
        'event_name',
        'context_snapshot',
        'started_at',
        'finished_at',
        'is_success',
        'error_message',
        'is_simulation',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'is_success' => 'boolean',
        'is_simulation' => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(BusinessRule::class, 'rule_id');
    }
}
