<?php

namespace App\Enums;

enum DocumentType: string
{
    case MaintenanceOrder = 'maintenance_order';
    case RentalSummary = 'rental_summary';
    case AssetSheet = 'asset_sheet';

    public function label(): string
    {
        return match ($this) {
            self::MaintenanceOrder => 'Ordem de Serviço',
            self::RentalSummary => 'Resumo de Locação',
            self::AssetSheet => 'Ficha do Patrimônio',
        };
    }

    public function template(): string
    {
        return config("documents.templates.{$this->value}");
    }
}
