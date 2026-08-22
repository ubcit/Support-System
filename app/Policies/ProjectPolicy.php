<?php

namespace App\Policies;

use App\Models\User;
use Modules\Projects\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasAnyPermission(['projects.view', 'projects.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage');
    }
}
