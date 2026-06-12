<?php

namespace App\Policies;

use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;

class ReceivableTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, ReceivableTitle $title): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, ReceivableTitle $title): bool
    {
        return $user->can('finance.manage');
    }

    public function markPaid(User $user, ReceivableTitle $title): bool
    {
        return $user->can('finance.manage');
    }

    public function generateCharge(User $user, ReceivableTitle $title): bool
    {
        return $user->can('finance.manage');
    }
}
