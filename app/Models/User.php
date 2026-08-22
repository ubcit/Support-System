<?php

namespace App\Models;

use App\Enums\UserApprovalStatus;
use App\Helpers\AvatarPalette;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Employees\Models\Employee;
use Modules\Security\Models\Role;

#[Fillable(['name', 'email', 'phone', 'password', 'avatar_path', 'status', 'rejection_message', 'reviewed_by', 'reviewed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function resolveEmployee(): ?Employee
    {
        if ($this->relationLoaded('employee') && $this->employee) {
            return $this->employee;
        }

        // Bypasses Employee's own "workspace" scope (BelongsToWorkspace):
        // determining *which* workspace a user belongs to inherently
        // requires looking up their Employee row unscoped first — scoping
        // this lookup would recurse into itself forever (the scope calls
        // back into resolveEmployee() to find the current workspace).
        $employee = $this->employee()->withoutGlobalScope('workspace')->first();

        if ($employee) {
            return $employee;
        }

        return Employee::withoutGlobalScope('workspace')->where('email', $this->email)->first();
    }

    /**
     * Roles that may access the admin (/admin) panel.
     *
     * @return list<string>
     */
    public static function adminRoleSlugs(): array
    {
        return Role::adminPanel();
    }

    public function hasRoleSlug(string $slug): bool
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return false;
        }

        return $employee->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRoleSlug(array $slugs): bool
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return false;
        }

        return $employee->roles()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Whether this user (via their Employee's Roles) has been granted the
     * given RBAC permission slug (e.g. "tasks.edit"). Backs every Policy in
     * app/Policies. A user with no linked Employee record has no roles and
     * therefore no permissions, by design (deny-by-default).
     */
    public function hasPermission(string $slug): bool
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return false;
        }

        return $employee->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    /**
     * @param  list<string>  $slugs
     */
    public function hasAnyPermission(array $slugs): bool
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return false;
        }

        return $employee->roles()
            ->whereHas('permissions', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasAnyRoleSlug(self::adminRoleSlugs());
    }

    public function canAccessWorkspace(): bool
    {
        return $this->resolveEmployee() !== null;
    }

    public function preferredHomeRoute(): string
    {
        if ($this->canAccessAdmin()) {
            return 'dashboard';
        }

        if ($this->canAccessWorkspace()) {
            return 'workspace.dashboard';
        }

        return 'dashboard';
    }

    public function initials(): string
    {
        $initials = AvatarPalette::initials((string) $this->name);

        return $initials !== '?' ? $initials : 'U';
    }

    public function avatarColorClasses(): string
    {
        return AvatarPalette::classes($this->id ?? $this->name);
    }

    public function primaryRoleLabel(): ?string
    {
        $employee = $this->resolveEmployee();

        if (! $employee) {
            return null;
        }

        $role = $employee->roles()->first();

        return $role?->name ?? $employee->role;
    }

    public function avatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        return null;
    }

    public function approvalStatus(): UserApprovalStatus
    {
        return match ($this->status) {
            UserApprovalStatus::Pending->value => UserApprovalStatus::Pending,
            UserApprovalStatus::Rejected->value => UserApprovalStatus::Rejected,
            default => UserApprovalStatus::Approved,
        };
    }

    public function isApproved(): bool
    {
        return $this->approvalStatus() === UserApprovalStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->approvalStatus() === UserApprovalStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->approvalStatus() === UserApprovalStatus::Rejected;
    }

    public function rejectionMessage(): ?string
    {
        return $this->rejection_message;
    }
}
