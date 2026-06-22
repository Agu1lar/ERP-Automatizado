<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed padrão: demo completa nas duas empresas operacionais.
     *
     * Outros seeders:
     * - FullDemoSeeder     → padrão (este)
     * - BulkDemoSeeder     → volume alto (centenas de registros)
     * - LargeDemoSeeder    → legado single-tenant
     */
    public function run(): void
    {
        $this->call([
            FullDemoSeeder::class,
        ]);
    }
}
