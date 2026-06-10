<?php

namespace App\Enums;

enum MaintenancePriority: string
{
    case Baixa = 'baixa';
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Baixa => 'Baixa',
            self::Normal => 'Normal',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }
}
