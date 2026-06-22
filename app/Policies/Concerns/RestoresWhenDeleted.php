<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait RestoresWhenDeleted
{
    abstract public function delete(User $user, mixed $model): bool;

    public function restore(User $user, mixed $model): bool
    {
        return $this->delete($user, $model);
    }
}
