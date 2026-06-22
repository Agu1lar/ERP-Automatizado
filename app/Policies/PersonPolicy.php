<?php

namespace App\Policies;

use App\Models\Domain\Person\Person;
use App\Models\User;
use App\Policies\Concerns\RestoresWhenDeleted;

class PersonPolicy
{
    use RestoresWhenDeleted;
    public function viewAny(User $user): bool
    {
        return $user->can('people.view');
    }

    public function view(User $user, Person $person): bool
    {
        return $user->can('people.view');
    }

    public function create(User $user): bool
    {
        return $user->can('people.manage');
    }

    public function update(User $user, Person $person): bool
    {
        return $user->can('people.manage');
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->can('people.manage');
    }
}
