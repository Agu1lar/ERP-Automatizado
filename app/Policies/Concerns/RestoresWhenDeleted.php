<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait RestoresWhenDeleted
{
    public function restore(User $user, mixed $model): bool
    {
        return $this->delete($user, $model);
    }
}
