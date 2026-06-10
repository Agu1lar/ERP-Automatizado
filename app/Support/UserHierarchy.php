<?php

namespace App\Support;

use App\Enums\HierarchyLevel;
use App\Enums\UserRole;
use App\Models\User;

class UserHierarchy
{
    public static function level(User $user): HierarchyLevel
    {
        if ($user->hasRole(UserRole::Admin->value)) {
            return HierarchyLevel::Admin;
        }

        if ($user->hasRole(UserRole::Gestor->value)) {
            return HierarchyLevel::Manager;
        }

        if ($user->hasAnyRole(UserRole::operationalRoles())) {
            return HierarchyLevel::Operator;
        }

        return HierarchyLevel::Operator;
    }

    public static function isAdmin(User $user): bool
    {
        return self::level($user) === HierarchyLevel::Admin;
    }

    public static function isManager(User $user): bool
    {
        return self::level($user) === HierarchyLevel::Manager;
    }

    public static function isOperational(User $user): bool
    {
        return $user->hasAnyRole(UserRole::operationalRoles());
    }

    public static function canCreateRecords(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Gestor->value,
            ...UserRole::operationalRoles(),
        ]);
    }

    public static function canManageCustomFields(User $user): bool
    {
        return $user->can('custom_fields.manage');
    }

    public static function canHideCustomFields(User $user): bool
    {
        return $user->can('custom_fields.hide');
    }
}
