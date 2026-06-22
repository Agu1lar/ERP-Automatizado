<?php

namespace App\Policies;

use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class EquipmentModelPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.models.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.models.manage');
    }

    public function update(User $user, EquipmentModel $model): bool
    {
        return $user->can('fleet.models.manage');
    }

    public function delete(User $user, EquipmentModel $model): bool
    {
        return $user->can('fleet.models.manage');
    }
}
