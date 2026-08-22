<?php

namespace Modules\Employees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Enums\SkillLevel;

class EmployeeSkill extends Model
{
    protected $fillable = [
        'employee_id',
        'skill',
        'level',
    ];

    protected $casts = [
        'level' => SkillLevel::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
