<?php

namespace Modules\Employees\Models;

use App\Helpers\AvatarPalette;
use App\Models\User;
use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Traits\BelongsToWorkspace;
use Modules\Projects\Models\Project;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskAssignment;

class Employee extends Model
{
    use BelongsToWorkspace, Filterable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'department',
        'is_available',
        'max_workload',
        'metadata',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'max_workload' => 'integer',
        'metadata' => 'array',
    ];

    protected array $filterable = [
        'role',
        'department',
        'is_available',
    ];

    protected array $searchable = [
        'name',
        'email',
        'phone',
        'role',
    ];

    protected array $sortable = [
        'name',
        'role',
        'created_at',
        'updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function initials(int $max = 1): string
    {
        return AvatarPalette::initials((string) $this->name, $max);
    }

    public function avatarUrl(): ?string
    {
        return $this->user?->avatarUrl();
    }

    public function avatarColorClasses(): string
    {
        return AvatarPalette::classes($this->user_id ?? $this->id ?? $this->name);
    }

    // workspace() relation is provided by the BelongsToWorkspace trait.

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'employee_role');
    }

    public function isPrivileged(): bool
    {
        $privileged = Role::privileged();

        $role = strtolower((string) $this->role);
        foreach ($privileged as $slug) {
            if (str_contains($role, $slug)) {
                return true;
            }
        }

        return $this->roles()
            ->whereIn('slug', $privileged)
            ->exists();
    }

    public function isBoss(): bool
    {
        if (stripos((string) $this->role, 'boss') !== false) {
            return true;
        }

        return $this->roles()
            ->where('slug', Role::BOSS)
            ->exists();
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'employee_project')
            ->withPivot('role', 'assigned_at');
    }

    public function reportedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'reported_by');
    }

    public function assignedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'assigned_to');
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Get the current active task count for workload calculation.
     */
    public function getCurrentWorkload(): int
    {
        return $this->taskAssignments()
            ->whereNull('unassigned_at')
            ->whereHas('task', function ($query) {
                $query->whereNotIn('status', ['done', 'cancelled']);
            })
            ->count();
    }

    /**
     * Check if the employee can take more tasks.
     */
    public function hasCapacity(): bool
    {
        return $this->getCurrentWorkload() < $this->max_workload;
    }
}
