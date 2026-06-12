<?php

namespace App\Enums;

enum DeliveryManifestStopType: string
{
    case Entrega = 'entrega';
    case Retirada = 'retirada';

    public function label(): string
    {
        return match ($this) {
            self::Entrega => 'Entrega',
            self::Retirada => 'Retirada / recolhida',
        };
    }
}
