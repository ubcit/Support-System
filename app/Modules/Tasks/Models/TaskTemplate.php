<?php

namespace Modules\Tasks\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'title',
        'description',
        'type',
        'workflow_id',
        'default_priority',
        'checklists_template',
        'metadata',
    ];

    protected $casts = [
        'checklists_template' => 'array',
        'metadata' => 'array',
    ];
}
