<?php

namespace App\Enums;

enum HierarchyLevel: int
{
    case Operator = 1;
    case Manager = 2;
    case Admin = 3;

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operacional',
            self::Manager => 'Gerencial',
            self::Admin => 'Administrador',
        };
    }
}
