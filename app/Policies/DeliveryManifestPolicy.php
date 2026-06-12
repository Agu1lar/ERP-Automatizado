<?php

namespace App\Policies;

use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\User;

class DeliveryManifestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rentals.view');
    }

    public function view(User $user, DeliveryManifest $manifest): bool
    {
        return $user->can('rentals.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('rentals.operate');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, DeliveryManifest $manifest): bool
    {
        return $this->manage($user);
    }
}
