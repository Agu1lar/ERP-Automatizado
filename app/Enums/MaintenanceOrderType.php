<?php

namespace App\Enums;

enum MaintenanceOrderType: string
{
    case Preventiva = 'preventiva';
    case Corretiva = 'corretiva';
    case RetornoLocacao = 'retorno_locacao';
    case Indenizacao = 'indenizacao';

    public function label(): string
    {
        return match ($this) {
            self::Preventiva => 'Preventiva',
            self::Corretiva => 'Corretiva',
            self::RetornoLocacao => 'Retorno de locação',
            self::Indenizacao => 'Indenização',
        };
    }

    public function isIndenizacao(): bool
    {
        return $this === self::Indenizacao;
    }
}
