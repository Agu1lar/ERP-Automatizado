<?php

namespace App\Enums;

enum RentalBillingQueueStatus: string
{
    case Pendente = 'pendente';
    case Autorizado = 'autorizado';
    case Faturado = 'faturado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Autorizado => 'Autorizado',
            self::Faturado => 'Faturado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendente => 'amber',
            self::Autorizado => 'blue',
            self::Faturado => 'green',
            self::Cancelado => 'gray',
        };
    }
}
