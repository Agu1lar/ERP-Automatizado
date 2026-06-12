<?php

namespace App\Enums;

enum PartPurchaseOrderStatus: string
{
    case Rascunho = 'rascunho';
    case Enviado = 'enviado';
    case RecebidoParcial = 'recebido_parcial';
    case Recebido = 'recebido';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviado => 'Enviado',
            self::RecebidoParcial => 'Recebido parcial',
            self::Recebido => 'Recebido',
            self::Cancelado => 'Cancelado',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Rascunho, self::Enviado, self::RecebidoParcial], true);
    }
}
