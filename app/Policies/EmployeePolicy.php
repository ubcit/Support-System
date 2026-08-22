<?php

namespace App\Policies;

use App\Models\User;
use Modules\Employees\Models\Employee;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage');
    }
}
