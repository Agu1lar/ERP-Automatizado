<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed mínimo para desenvolvimento (2 empresas, poucos registros).
     *
     * Outros seeders em database/seeders/:
     * - BulkDemoSeeder     → demo massiva: empresas, pessoas, clientes, preços, frota variada, locações e manutenção
     * - LargeDemoSeeder    → demo médio (legado, sem multi-empresa completo)
     * - DemoDataSeeder     → legado mínimo
     * - CategorySeeder     → legado (sem operating_company)
     * - CompanySeeder      → cadastro de empresas (pessoas), não confundir com OperatingCompany
     *
     * Carga massiva: php artisan db:seed --class=BulkDemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OperatingCompanySeeder::class,
            EquipmentCategorySeeder::class,
            EquipmentModelSeeder::class,
            AssetSeeder::class,
            CustomerSeeder::class,
            RentalSeeder::class,
            MaintenanceOrderSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
