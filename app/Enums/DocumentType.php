<?php

namespace App\Enums;

enum DocumentType: string
{
    case MaintenanceOrder = 'maintenance_order';
    case RentalSummary = 'rental_summary';
    case RentalContract = 'rental_contract';
    case AssetSheet = 'asset_sheet';
    case BillingInvoice = 'billing_invoice';

    public function label(): string
    {
        return match ($this) {
            self::MaintenanceOrder => 'Ordem de Serviço',
            self::RentalSummary => 'Resumo de Locação',
            self::RentalContract => 'Contrato de Locação',
            self::AssetSheet => 'Ficha do Patrimônio',
            self::BillingInvoice => 'Fatura de Locação',
        };
    }

    public function template(): string
    {
        return config("documents.templates.{$this->value}");
    }
}
