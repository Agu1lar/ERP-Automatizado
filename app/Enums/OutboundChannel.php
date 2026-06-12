<?php

namespace App\Enums;

enum OutboundChannel: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Sms => 'SMS',
        };
    }
}
