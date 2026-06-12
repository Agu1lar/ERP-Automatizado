<?php

namespace App\Enums;

enum FiscalDocumentType: string
{
    case Nfse = 'nfse';
    case NfRemessa = 'nf_remessa';
    case NfRetorno = 'nf_retorno';

    public function label(): string
    {
        return match ($this) {
            self::Nfse => 'NFS-e (serviço)',
            self::NfRemessa => 'NF remessa',
            self::NfRetorno => 'NF retorno',
        };
    }
}
