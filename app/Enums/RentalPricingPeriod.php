<?php

namespace App\Enums;

enum RentalPricingPeriod: string
{
    case Diaria = 'diaria';
    case Semanal = 'semanal';
    case Mensal = 'mensal';

    public function label(): string
    {
        return match ($this) {
            self::Diaria => 'Diária',
            self::Semanal => 'Semanal',
            self::Mensal => 'Mensal',
        };
    }

    public function unitLabel(): string
    {
        return match ($this) {
            self::Diaria => 'dia',
            self::Semanal => 'semana',
            self::Mensal => 'mês',
        };
    }
}
