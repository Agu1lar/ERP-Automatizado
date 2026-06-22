<?php

namespace App\Policies;

use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class DeliveryVehiclePolicy
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

    public function update(User $user, DeliveryVehicle $vehicle): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, DeliveryVehicle $vehicle): bool
    {
        return $this->manage($user);
    }
}
