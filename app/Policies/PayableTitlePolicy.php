<?php

namespace App\Policies;

use App\Models\Domain\Finance\PayableTitle;
use App\Models\User;

class PayableTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, PayableTitle $title): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, PayableTitle $title): bool
    {
        return $user->can('finance.manage');
    }

    public function markPaid(User $user, PayableTitle $title): bool
    {
        return $user->can('finance.manage');
    }
}
