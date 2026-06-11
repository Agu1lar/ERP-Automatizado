<?php

namespace App\Enums;

enum LateFeeRuleScope: string
{
    case Global = 'global';
    case Customer = 'customer';
    case Rental = 'rental';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global (todos os títulos)',
            self::Customer => 'Cliente específico',
            self::Rental => 'Contrato / locação específica',
        };
    }
}
