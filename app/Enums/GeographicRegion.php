<?php

namespace App\Enums;

enum GeographicRegion: string
{
    case Bh = 'bh';
    case Rmbh = 'rmbh';
    case Interior = 'interior';
    case Indefinido = 'indefinido';

    public function label(): string
    {
        return match ($this) {
            self::Bh => 'BH (capital)',
            self::Rmbh => 'RMBH',
            self::Interior => 'Interior MG',
            self::Indefinido => 'Não classificado',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Bh => 'BH',
            self::Rmbh => 'RMBH',
            self::Interior => 'Interior',
            self::Indefinido => '—',
        };
    }
}
