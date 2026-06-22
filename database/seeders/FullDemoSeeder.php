<?php

namespace Database\Seeders;

/**
 * Demo robusta — dezenas de registros em cada módulo, nas duas empresas operacionais.
 *
 * Por empresa (aprox.): ~96 patrimônios, ~120 locações, ~20 OS abertas (total),
 * ~24 orçamentos, ~10 pedidos de compra, 3 motoristas, 24 peças no catálogo.
 *
 * Uso: php artisan migrate:fresh --force --seed
 *      php artisan db:seed --class=FullDemoSeeder
 */
class FullDemoSeeder extends BulkDemoSeeder
{
    protected int $customersTarget = 80;

    protected int $companiesTarget = 36;

    protected int $peopleTarget = 60;

    protected int $assetsPerCompany = 96;

    protected int $openOrdersTotal = 40;

    protected int $completedOrdersTarget = 50;

    protected string $rentalProfile = 'standard';

    protected bool $seedSupplemental = true;

    protected int $driversPerCompany = 3;

    protected int $quotesRascunhoPerCompany = 12;

    protected int $quotesEnviadoPerCompany = 8;

    protected int $quotesExpiradoPerCompany = 4;

    protected int $purchaseOrdersPerCompany = 10;

    protected int $preventiveOrdersPerCompany = 8;

    protected int $partCatalogItemCount = 24;

    protected int $suppliersTarget = 12;
}
