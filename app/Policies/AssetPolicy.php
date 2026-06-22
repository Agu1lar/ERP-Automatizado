<?php

namespace App\Policies;

use App\Models\Domain\Fleet\Asset;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class AssetPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('fleet.assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fleet.assets.manage');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.manage') || $user->can('records.edit');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.manage');
    }

    public function changeStatus(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.change_status');
    }

    public function manageAttachments(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.attachments');
    }

    public function updatePurchaseValue(User $user, Asset $asset): bool
    {
        return $user->can('fleet.assets.manage');
    }
}
