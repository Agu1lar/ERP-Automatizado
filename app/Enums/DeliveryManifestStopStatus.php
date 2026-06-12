<?php

namespace App\Enums;

enum DeliveryManifestStopStatus: string
{
    case Pendente = 'pendente';
    case Concluida = 'concluida';
    case NaoRealizada = 'nao_realizada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Concluida => 'Concluída',
            self::NaoRealizada => 'Não realizada',
        };
    }
}
