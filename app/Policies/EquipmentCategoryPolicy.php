<?php

namespace App\Policies;

use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class EquipmentCategoryPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.categories.view');
    }

    public function view(User $user, EquipmentCategory $category): bool
    {
        return $user->can('fleet.categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.categories.manage');
    }

    public function update(User $user, EquipmentCategory $category): bool
    {
        return $user->can('fleet.categories.manage');
    }

    public function delete(User $user, EquipmentCategory $category): bool
    {
        return $user->can('fleet.categories.manage');
    }
}
