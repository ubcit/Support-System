<?php

namespace App\Policies;

use App\Models\User;
use Modules\Customers\Models\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers.manage');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.manage');
    }
}
