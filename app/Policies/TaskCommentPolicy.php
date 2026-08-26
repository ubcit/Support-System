<?php

namespace App\Policies;

use App\Models\User;
use Modules\Tasks\Models\TaskComment;

class TaskCommentPolicy
{
    public function delete(User $user, TaskComment $comment): bool
    {
        $employee = $user->resolveEmployee();
        if (! $employee) {
            return false;
        }

        if ($employee->isPrivileged()) {
            return true;
        }

        return (int) $comment->employee_id === (int) $employee->id;
    }
}
