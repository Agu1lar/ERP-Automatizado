<?php

namespace App\Policies;

use App\Models\Domain\Customer\Customer;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class CustomerPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }
}
