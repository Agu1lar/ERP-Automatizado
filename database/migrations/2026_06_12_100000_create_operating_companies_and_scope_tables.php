<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_companies', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->string('endereco')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::table('operating_companies')->insert([
            [
                'id' => 1,
                'nome' => 'Acesso Equipamentos',
                'slug' => 'acesso',
                'razao_social' => 'Acesso Equipamentos Ltda',
                'cnpj' => '',
                'endereco' => 'Belo Horizonte, MG',
                'telefone' => '',
                'email' => '',
                'logo_path' => 'stack/assets/logo.png',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nome' => 'Super Máquinas',
                'slug' => 'supermaquinas',
                'razao_social' => 'Super Máquinas Ltda',
                'cnpj' => '',
                'endereco' => '',
                'telefone' => '',
                'email' => '',
                'logo_path' => null,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $scopedTables = [
            'equipment_categories',
            'equipment_models',
            'equipment_pricings',
            'assets',
            'rentals',
            'receivable_titles',
            'rental_billing_queue',
            'maintenance_orders',
            'part_catalog_items',
            'preventive_maintenance_rules',
        ];

        foreach ($scopedTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'operating_company_id')) {
                    $table->foreignId('operating_company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('operating_companies');
                }
            });

            DB::table($tableName)->whereNull('operating_company_id')->update(['operating_company_id' => 1]);
        }

        if (Schema::hasTable('rentals')) {
            \App\Support\RentalActiveIndex::recreate();
        }
    }

    public function down(): void
    {
        $scopedTables = [
            'preventive_maintenance_rules',
            'part_catalog_items',
            'maintenance_orders',
            'rental_billing_queue',
            'receivable_titles',
            'rentals',
            'assets',
            'equipment_pricings',
            'equipment_models',
            'equipment_categories',
        ];

        foreach ($scopedTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'operating_company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('operating_company_id');
            });
        }

        Schema::dropIfExists('operating_companies');
    }
};
