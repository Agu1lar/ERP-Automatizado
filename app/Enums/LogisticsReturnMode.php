<?php

namespace App\Enums;

enum LogisticsReturnMode: string
{
    case EmpresaRecolhe = 'empresa_recolhe';
    case ClienteDevolve = 'cliente_devolve';

    public function label(): string
    {
        return match ($this) {
            self::EmpresaRecolhe => 'Recolhida pela empresa',
            self::ClienteDevolve => 'Cliente devolve no pátio',
        };
    }

    public function isCompanyHandled(): bool
    {
        return $this === self::EmpresaRecolhe;
    }
}
