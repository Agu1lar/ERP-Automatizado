<?php

namespace App\Enums;

enum PayableTitleStatus: string
{
    case Aberto = 'aberto';
    case Pago = 'pago';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Pago => 'Pago',
            self::Cancelado => 'Cancelado',
        };
    }
}
