<?php

namespace App\Enums;

enum QrCodeStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Generated => 'Gerado',
            self::Failed => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Generated => 'green',
            self::Failed => 'red',
        };
    }
}
