<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Reservado = 'reservado';
    case Locado = 'locado';
    case EmInspecao = 'em_inspecao';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Reservado => 'Reservado',
            self::Locado => 'Locado',
            self::EmInspecao => 'Em inspeção',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reservado => 'blue',
            self::Locado => 'indigo',
            self::EmInspecao => 'yellow',
            self::Concluido => 'green',
            self::Cancelado => 'gray',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Reservado, self::Locado, self::EmInspecao], true);
    }
}
