<?php

namespace App\Enums;

enum DeliveryManifestStatus: string
{
    case Rascunho = 'rascunho';
    case EmRota = 'em_rota';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmRota => 'Em rota',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
        };
    }
}
