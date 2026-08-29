<?php

namespace App\Policies;

use App\Models\User;
use Modules\Tasks\Models\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.view')) {
            return false;
        }

        $employee = $user->resolveEmployee();
        if (! $employee) {
            return false;
        }

        if ($employee->isPrivileged()) {
            return true;
        }

        // Employee: assigned or same project
        if ($task->assignees()->where('employees.id', $employee->id)->exists()) {
            return true;
        }

        if ($task->project_id) {
            return $employee->projects()->where('projects.id', $task->project_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.edit')) {
            return false;
        }

        $employee = $user->resolveEmployee();
        if (! $employee) {
            return false;
        }

        // Privileged roles can edit any task
        if ($employee->isPrivileged()) {
            return true;
        }

        // Employees can only edit tasks assigned to them
        return $task->assignees()->where('employees.id', $employee->id)->exists();
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.delete');
    }

    public function restore(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.delete')) {
            return false;
        }

        // Trash (restore / permanent delete) is manager-only.
        return $user->resolveEmployee()?->isPrivileged() ?? false;
    }

    public function forceDelete(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.delete')) {
            return false;
        }

        return $user->resolveEmployee()?->isPrivileged() ?? false;
    }
}
