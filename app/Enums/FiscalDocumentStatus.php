<?php

namespace App\Enums;

enum FiscalDocumentStatus: string
{
    case Pendente = 'pendente';
    case EnviadoErp = 'enviado_erp';
    case Emitido = 'emitido';
    case Erro = 'erro';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente no ERP',
            self::EnviadoErp => 'Enviado ao ERP',
            self::Emitido => 'Emitido',
            self::Erro => 'Erro',
        };
    }
}
