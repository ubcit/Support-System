<?php

namespace Modules\Statistics\Models;

use Illuminate\Database\Eloquent\Model;

class KPI extends Model
{
    protected $table = 'kpis';

    protected $fillable = [
        'name',
        'entity_type',
        'calculation_rule',
        'threshold',
        'alert_action',
        'is_active',
    ];

    protected $casts = [
        'calculation_rule' => 'array',
        'threshold' => 'array',
        'alert_action' => 'array',
        'is_active' => 'boolean',
    ];
}
