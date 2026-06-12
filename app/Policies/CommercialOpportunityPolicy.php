<?php

namespace App\Policies;

use App\Models\Domain\Crm\CommercialOpportunity;
use App\Models\User;

class CommercialOpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.view');
    }

    public function view(User $user, CommercialOpportunity $opportunity): bool
    {
        return $user->can('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->can('crm.manage');
    }

    public function update(User $user, CommercialOpportunity $opportunity): bool
    {
        return $user->can('crm.manage');
    }
}
