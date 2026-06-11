<?php

namespace App\Enums;

enum LogisticsDeliveryMode: string
{
    case EmpresaEntrega = 'empresa_entrega';
    case ClienteRetira = 'cliente_retira';

    public function label(): string
    {
        return match ($this) {
            self::EmpresaEntrega => 'Entrega pela empresa',
            self::ClienteRetira => 'Cliente retira no pátio',
        };
    }

    public function isCompanyHandled(): bool
    {
        return $this === self::EmpresaEntrega;
    }
}
