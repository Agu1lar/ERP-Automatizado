<?php

namespace App\Enums;

enum CompanyType: string
{
    case Propria = 'propria';
    case Externa = 'externa';
    case Cliente = 'cliente';
    case Fornecedor = 'fornecedor';

    public function label(): string
    {
        return match ($this) {
            self::Propria => 'Empresa própria',
            self::Externa => 'Empresa externa',
            self::Cliente => 'Cliente',
            self::Fornecedor => 'Fornecedor de peças',
        };
    }
}
