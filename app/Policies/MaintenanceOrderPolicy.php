<?php

namespace App\Policies;

use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;

class MaintenanceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function view(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.manage');
    }

    public function update(User $user, MaintenanceOrder $order): bool
    {
        return ($user->can('maintenance.manage') || $user->can('records.edit'))
            && $order->statusEnum()->isOpen();
    }

    public function operate(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('maintenance.operate') && $order->statusEnum()->isOpen();
    }
}
