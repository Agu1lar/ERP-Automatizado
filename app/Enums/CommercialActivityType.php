<?php

namespace App\Enums;

enum CommercialActivityType: string
{
    case Ligacao = 'ligacao';
    case Visita = 'visita';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Nota = 'nota';

    public function label(): string
    {
        return match ($this) {
            self::Ligacao => 'Ligação',
            self::Visita => 'Visita',
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Nota => 'Nota',
        };
    }
}
