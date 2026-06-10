<?php

namespace App\Enums;

enum CustomFieldEntity: string
{
    case Asset = 'asset';
    case Rental = 'rental';
    case MaintenanceOrder = 'maintenance_order';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Patrimônio',
            self::Rental => 'Locação',
            self::MaintenanceOrder => 'Ordem de serviço',
        };
    }
}
