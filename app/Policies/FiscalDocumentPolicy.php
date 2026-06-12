<?php

namespace App\Policies;

use App\Models\Domain\Fiscal\FiscalDocument;
use App\Models\User;

class FiscalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, FiscalDocument $document): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, FiscalDocument $document): bool
    {
        return $user->can('finance.manage');
    }
}
