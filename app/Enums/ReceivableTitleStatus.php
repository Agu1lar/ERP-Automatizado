<?php

namespace App\Enums;

enum ReceivableTitleStatus: string
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

    public function color(): string
    {
        return match ($this) {
            self::Aberto => 'blue',
            self::Pago => 'green',
            self::Cancelado => 'gray',
        };
    }
}
