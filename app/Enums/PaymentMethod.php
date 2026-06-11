<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case Transferencia = 'transferencia';
    case Boleto = 'boleto';
    case Cartao = 'cartao';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'PIX',
            self::Transferencia => 'Transferência',
            self::Boleto => 'Boleto',
            self::Cartao => 'Cartão',
            self::Outro => 'Outro',
        };
    }
}
