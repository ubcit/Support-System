<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowState extends Model
{
    protected $fillable = [
        'workflow_id',
        'name',
        'type',
        'order',
        'wip_limit',
    ];

    protected $casts = [
        'order' => 'integer',
        'wip_limit' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
