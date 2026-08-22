<?php

namespace Modules\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

class EmployeeTaskState extends Model
{
    protected $fillable = [
        'employee_id',
        'task_id',
        'is_bookmarked',
        'is_pinned',
        'last_viewed_at',
    ];

    protected $casts = [
        'is_bookmarked' => 'boolean',
        'is_pinned' => 'boolean',
        'last_viewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
