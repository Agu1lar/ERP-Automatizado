<?php

namespace App\Policies;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class OperatingCompanyPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('admin.companies.manage');
    }

    public function update(User $user, OperatingCompany $company): bool
    {
        return $user->can('admin.companies.manage');
    }

    public function delete(User $user, OperatingCompany $company): bool
    {
        return $user->can('admin.companies.manage')
            && OperatingCompany::query()->count() > 1;
    }
}
