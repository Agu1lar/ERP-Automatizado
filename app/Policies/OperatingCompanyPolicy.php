<?php

namespace App\Policies;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;

class OperatingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.companies.manage');
    }

    public function update(User $user, OperatingCompany $company): bool
    {
        return $user->can('admin.companies.manage');
    }
}
