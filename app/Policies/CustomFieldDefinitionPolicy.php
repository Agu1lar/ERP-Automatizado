<?php

namespace App\Policies;

use App\Models\Domain\CustomField\CustomFieldDefinition;
use App\Models\User;

class CustomFieldDefinitionPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('custom_fields.manage');
    }

    public function hide(User $user): bool
    {
        return $user->can('custom_fields.hide');
    }

    public function delete(User $user, CustomFieldDefinition $definition): bool
    {
        return $user->can('admin.users.manage');
    }
}
