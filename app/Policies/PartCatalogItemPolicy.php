<?php

namespace App\Policies;

use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\User;

class PartCatalogItemPolicy
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

    public function update(User $user, PartCatalogItem $item): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, PartCatalogItem $item): bool
    {
        return $this->manage($user);
    }
}
