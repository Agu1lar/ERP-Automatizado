<?php

namespace App\Policies;

use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Rental\Rental;
use App\Models\User;

class RentalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rentals.view');
    }

    public function view(User $user, Rental $rental): bool
    {
        return $user->can('rentals.view');
    }

    public function reserve(User $user): bool
    {
        return $user->can('rentals.reserve');
    }

    public function operate(User $user, Rental $rental): bool
    {
        return $user->can('rentals.operate');
    }

    public function cancel(User $user, Rental $rental): bool
    {
        return $user->can('rentals.reserve') || $user->can('rentals.operate');
    }

    public function updateFicha(User $user, Rental $rental): bool
    {
        return $user->can('records.edit')
            || $user->can('rentals.operate')
            || $user->can('rentals.reserve')
            || $user->can('customers.manage');
    }

    public function transferCommercialUser(User $user, Rental $rental): bool
    {
        return $rental->status === RentalStatus::Concluido->value
            && $user->hasAnyRole([UserRole::Admin->value, UserRole::Gestor->value]);
    }

    public function manageAttachments(User $user, Rental $rental): bool
    {
        return $user->can('updateFicha', $rental);
    }
}
