<?php

namespace App\Enums;

enum RentalQuoteStatus: string
{
    case Rascunho = 'rascunho';
    case Enviado = 'enviado';
    case Convertido = 'convertido';
    case Expirado = 'expirado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviado => 'Enviado',
            self::Expirado => 'Expirado',
            self::Convertido => 'Convertido',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rascunho => 'gray',
            self::Enviado => 'blue',
            self::Expirado => 'amber',
            self::Convertido => 'green',
            self::Cancelado => 'gray',
        };
    }

    public function canConvert(): bool
    {
        return $this === self::Enviado;
    }

    public function canEdit(): bool
    {
        return $this === self::Rascunho;
    }
}
