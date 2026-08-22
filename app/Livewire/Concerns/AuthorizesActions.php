<?php

namespace App\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Security\Models\Role;

trait AuthorizesActions
{
    /**
     * Check if the current user has the given permission slug.
     * Aborts with 403 when denied — consistent with the Gate::authorize()
     * calls used by the API controllers and policies.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasPermission($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    public function isManager(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $privileged = Role::privileged();

        if (method_exists($user, 'hasAnyRoleSlug') && $user->hasAnyRoleSlug($privileged)) {
            return true;
        }

        $role = strtolower($user->resolveEmployee()?->role ?? '');

        foreach ($privileged as $slug) {
            if (str_contains($role, $slug)) {
                return true;
            }
        }

        return false;
    }
}
