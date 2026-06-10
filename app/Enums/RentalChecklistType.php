<?php

namespace App\Enums;

enum RentalChecklistType: string
{
    case Saida = 'saida';
    case Retorno = 'retorno';

    public function label(): string
    {
        return match ($this) {
            self::Saida => 'Saída',
            self::Retorno => 'Retorno',
        };
    }
}
