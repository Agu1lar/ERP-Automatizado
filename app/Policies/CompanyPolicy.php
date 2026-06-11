<?php

namespace App\Policies;

use App\Models\Domain\Person\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('people.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can('people.view');
    }

    public function create(User $user): bool
    {
        return $user->can('people.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('people.manage');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('people.manage');
    }
}
