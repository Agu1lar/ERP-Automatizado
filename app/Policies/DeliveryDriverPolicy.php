<?php

namespace App\Policies;

use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class DeliveryDriverPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('rentals.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('fleet.assets.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, DeliveryDriver $driver): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, DeliveryDriver $driver): bool
    {
        return $this->manage($user);
    }
}
