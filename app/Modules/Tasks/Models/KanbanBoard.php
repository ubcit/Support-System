<?php

namespace Modules\Tasks\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KanbanBoard extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'project_id',
        'employee_id',
        'title',
        'columns_config',
        'swimlane',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'columns_config' => 'array',
        'filters' => 'array',
        'is_default' => 'boolean',
    ];
}
