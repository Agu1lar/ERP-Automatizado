<?php

namespace App\Enums;

enum RentalBillingQueueType: string
{
    case Locacao = 'locacao';
    case Renovacao = 'renovacao';
    case Indenizacao = 'indenizacao';
    case FreteEntrega = 'frete_entrega';
    case FreteRecolhida = 'frete_recolhida';

    public function label(): string
    {
        return match ($this) {
            self::Locacao => 'Locação',
            self::Renovacao => 'Renovação de locação',
            self::Indenizacao => 'Indenização',
            self::FreteEntrega => 'Frete de entrega',
            self::FreteRecolhida => 'Frete de recolhida',
        };
    }
}
