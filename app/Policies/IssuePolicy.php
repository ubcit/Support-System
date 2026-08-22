<?php

namespace App\Policies;

use App\Models\User;
use Modules\Issues\Models\Issue;

class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['issues.view', 'issues.manage']);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->hasAnyPermission(['issues.view', 'issues.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('issues.manage');
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->hasPermission('issues.manage');
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->hasPermission('issues.manage');
    }
}
