<?php

namespace App\Policies;

use App\Models\Domain\Logistics\Yard;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class YardPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.assets.view');
    }

    public function view(User $user, Yard $yard): bool
    {
        return $user->can('fleet.assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.assets.manage');
    }

    public function update(User $user, Yard $yard): bool
    {
        return $user->can('fleet.assets.manage');
    }

    public function delete(User $user, Yard $yard): bool
    {
        return $user->can('fleet.assets.manage');
    }
}
