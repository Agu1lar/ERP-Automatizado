<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
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
            'late_fee_rules',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'operating_company_id')) {
                continue;
            }

            DB::table($tableName)->whereNull('operating_company_id')->update(['operating_company_id' => 1]);
        }

        if (
            Schema::hasTable('equipment_models')
            && Schema::hasTable('equipment_categories')
            && Schema::hasColumn('equipment_models', 'operating_company_id')
            && Schema::hasColumn('equipment_categories', 'operating_company_id')
        ) {
            $models = DB::table('equipment_models')
                ->select('equipment_models.id', 'equipment_categories.operating_company_id as category_company_id', 'equipment_models.operating_company_id as model_company_id')
                ->join('equipment_categories', 'equipment_categories.id', '=', 'equipment_models.equipment_category_id')
                ->get();

            foreach ($models as $row) {
                if ((int) $row->model_company_id !== (int) $row->category_company_id) {
                    DB::table('equipment_models')
                        ->where('id', $row->id)
                        ->update(['operating_company_id' => $row->category_company_id]);
                }
            }
        }

        if (
            Schema::hasTable('assets')
            && Schema::hasTable('equipment_models')
            && Schema::hasColumn('assets', 'operating_company_id')
            && Schema::hasColumn('equipment_models', 'operating_company_id')
        ) {
            $assets = DB::table('assets')
                ->select('assets.id', 'equipment_models.operating_company_id as model_company_id')
                ->join('equipment_models', 'equipment_models.id', '=', 'assets.equipment_model_id')
                ->whereNull('assets.operating_company_id')
                ->get();

            foreach ($assets as $row) {
                DB::table('assets')
                    ->where('id', $row->id)
                    ->update(['operating_company_id' => $row->model_company_id]);
            }
        }
    }

    public function down(): void
    {
        // Dados de correção — sem reversão automática.
    }
};
