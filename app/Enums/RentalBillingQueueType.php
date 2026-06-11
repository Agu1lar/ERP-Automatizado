<?php

namespace App\Enums;

enum RentalBillingQueueType: string
{
    case Locacao = 'locacao';
    case Renovacao = 'renovacao';
    case Indenizacao = 'indenizacao';

    public function label(): string
    {
        return match ($this) {
            self::Locacao => 'Locação',
            self::Renovacao => 'Renovação de locação',
            self::Indenizacao => 'Indenização',
        };
    }
}
