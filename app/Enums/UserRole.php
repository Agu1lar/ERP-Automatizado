<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Gestor = 'gestor';
    case Comercial = 'comercial';
    case Operacao = 'operacao';
    case Manutencao = 'manutencao';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Gestor => 'Gestor',
            self::Comercial => 'Comercial',
            self::Operacao => 'Operação/Pátio',
            self::Manutencao => 'Manutenção',
        };
    }

    /** @return list<string> */
    public static function operationalRoles(): array
    {
        return [
            self::Comercial->value,
            self::Operacao->value,
            self::Manutencao->value,
        ];
    }
}
