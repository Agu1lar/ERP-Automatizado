<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retenção de registros arquivados (dias)
    |--------------------------------------------------------------------------
    |
    | Após arquivar (soft delete), o registro permanece recuperável por este
    | período. Depois, o comando archive:purge remove definitivamente.
    |
    */
    'retention_days' => (int) env('ARCHIVE_RETENTION_DAYS', 30),

    'models' => [
        \App\Models\Domain\Person\Company::class,
        \App\Models\Domain\Person\Person::class,
        \App\Models\Domain\Customer\Customer::class,
        \App\Models\User::class,
        \App\Models\Domain\Organization\OperatingCompany::class,
        \App\Models\Domain\Fleet\EquipmentCategory::class,
        \App\Models\Domain\Fleet\EquipmentModel::class,
        \App\Models\Domain\Fleet\Asset::class,
        \App\Models\Domain\Logistics\Yard::class,
        \App\Models\Domain\Logistics\DeliveryDriver::class,
        \App\Models\Domain\Logistics\DeliveryVehicle::class,
        \App\Models\Domain\Maintenance\PartCatalogItem::class,
        \App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class,
    ],

];
