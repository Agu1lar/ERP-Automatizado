<?php

namespace App\Enums;

enum PartStockMovementType: string
{
    case Entrada = 'entrada';
    case SaidaOs = 'saida_os';
    case Ajuste = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::SaidaOs => 'Saída (OS)',
            self::Ajuste => 'Ajuste manual',
        };
    }
}
