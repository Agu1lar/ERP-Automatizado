<?php

namespace App\Enums;

enum LogisticsShift: string
{
    case Manha = 'manha';
    case Tarde = 'tarde';
    case Combinar = 'combinar';

    public function label(): string
    {
        return match ($this) {
            self::Manha => 'Manhã',
            self::Tarde => 'Tarde',
            self::Combinar => 'A combinar',
        };
    }
}
