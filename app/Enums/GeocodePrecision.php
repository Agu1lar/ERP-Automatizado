<?php

namespace App\Enums;

enum GeocodePrecision: string
{
    case Street = 'street';
    case Approximate = 'approximate';
    case City = 'city';
    case Region = 'region';

    public function label(): string
    {
        return match ($this) {
            self::Street => 'Endereço',
            self::Approximate => 'Aproximado',
            self::City => 'Cidade',
            self::Region => 'Região',
        };
    }

    public function isHighAccuracy(): bool
    {
        return in_array($this, [self::Street, self::Approximate], true);
    }
}
