<?php

namespace Modules\Projects\Models;

use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Traits\BelongsToWorkspace;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Tasks\Models\Task;

class Project extends Model
{
    use BelongsToWorkspace, Filterable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'code',
        'description',
        'customer_id',
        'category_id',
        'status',
        'contact_phone',
        'settings',
        'started_at',
        'deadline_at',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'settings' => 'array',
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    protected array $filterable = [
        'status',
        'customer_id',
        'category_id',
    ];

    protected array $searchable = [
        'name',
        'code',
        'description',
    ];

    protected array $sortable = [
        'name',
        'status',
        'started_at',
        'deadline_at',
        'created_at',
        'updated_at',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_project')
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'category_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProjectAlias::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_project')
            ->withPivot('role', 'assigned_at');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
