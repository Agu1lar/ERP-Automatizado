<?php

namespace App\Enums;

enum PayableTitleOrigin: string
{
    case FornecedorPecas = 'fornecedor_pecas';
    case OficinaExterna = 'oficina_externa';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::FornecedorPecas => 'Fornecedor de peças',
            self::OficinaExterna => 'Oficina externa',
            self::Manual => 'Lançamento manual',
        };
    }
}
