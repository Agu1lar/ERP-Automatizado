<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class UserPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('admin.users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('admin.users.manage');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('admin.users.manage') && $user->id !== $model->id;
    }
}
