<?php

namespace App\Policies;

use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\User;

class EquipmentPricingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pricing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pricing.manage');
    }

    public function update(User $user, EquipmentPricing $pricing): bool
    {
        return $user->can('pricing.manage');
    }

    public function delete(User $user, EquipmentPricing $pricing): bool
    {
        return $user->can('pricing.manage');
    }
}
