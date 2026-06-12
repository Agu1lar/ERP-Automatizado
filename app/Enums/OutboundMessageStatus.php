<?php

namespace App\Enums;

enum OutboundMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Sent => 'Enviado',
            self::Failed => 'Falhou',
            self::Skipped => 'Ignorado',
        };
    }
}
