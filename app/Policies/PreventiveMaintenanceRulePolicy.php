<?php

namespace App\Policies;

use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\User;

class PreventiveMaintenanceRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('maintenance.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, PreventiveMaintenanceRule $rule): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, PreventiveMaintenanceRule $rule): bool
    {
        return $this->manage($user);
    }
}
