<?php

namespace App\Policies;

use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\User;

class PartPurchaseOrderPolicy
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

    public function update(User $user, PartPurchaseOrder $order): bool
    {
        return $this->manage($user);
    }
}
