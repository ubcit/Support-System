<?php

namespace Modules\Rules\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessRule extends Model
{
    use HasFactory;

    protected $table = 'business_rules';

    protected $fillable = [
        'name',
        'event_name',
        'conditions',
        'actions',
        'priority',
        'stop_processing',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'stop_processing' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(RuleExecution::class, 'rule_id');
    }

    public function getEventTriggerAttribute(): ?string
    {
        return $this->attributes['event_name'] ?? null;
    }

    public function setEventTriggerAttribute(?string $value): void
    {
        $this->attributes['event_name'] = $value;
    }
}
