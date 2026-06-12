<?php

namespace App\Enums;

enum MaintenanceOrderType: string
{
    case Preventiva = 'preventiva';
    case Corretiva = 'corretiva';
    case Campo = 'campo';
    case RetornoLocacao = 'retorno_locacao';
    case Indenizacao = 'indenizacao';

    public function label(): string
    {
        return match ($this) {
            self::Preventiva => 'Preventiva',
            self::Corretiva => 'Corretiva',
            self::Campo => 'Manutenção em campo',
            self::RetornoLocacao => 'Retorno de locação',
            self::Indenizacao => 'Indenização',
        };
    }

    public function isField(): bool
    {
        return $this === self::Campo;
    }

    public function isIndenizacao(): bool
    {
        return $this === self::Indenizacao;
    }
}
