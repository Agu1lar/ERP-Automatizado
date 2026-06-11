<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compostos para consultas filtradas pelo escopo global operating_company_id.
 * Evita full scan conforme auditoria, títulos e filas crescem.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: list<string>, 2: string}> */
    private array $indexes = [
        ['rentals', ['operating_company_id', 'status'], 'rentals_oc_status_idx'],
        ['rentals', ['operating_company_id', 'next_billing_at'], 'rentals_oc_next_billing_idx'],
        ['receivable_titles', ['operating_company_id', 'status'], 'recv_titles_oc_status_idx'],
        ['receivable_titles', ['operating_company_id', 'status', 'vencimento'], 'recv_titles_oc_status_due_idx'],
        ['rental_billing_queue', ['operating_company_id', 'status'], 'billing_queue_oc_status_idx'],
        ['maintenance_orders', ['operating_company_id', 'status'], 'maint_orders_oc_status_idx'],
        ['assets', ['operating_company_id', 'status'], 'assets_oc_status_idx'],
        ['equipment_models', ['operating_company_id', 'ativo'], 'equip_models_oc_active_idx'],
        ['equipment_categories', ['operating_company_id', 'ativo'], 'equip_cats_oc_active_idx'],
        ['equipment_pricings', ['operating_company_id', 'equipment_model_id'], 'equip_prices_oc_model_idx'],
        ['part_catalog_items', ['operating_company_id', 'ativo'], 'parts_oc_active_idx'],
        ['preventive_maintenance_rules', ['operating_company_id', 'ativo'], 'preventive_oc_active_idx'],
        ['late_fee_rules', ['operating_company_id', 'ativo'], 'late_fee_oc_active_idx'],
        ['rental_quotes', ['operating_company_id', 'status'], 'rental_quotes_oc_status_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'operating_company_id')) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue 2;
                }
            }

            Schema::table($table, function (Blueprint $table) use ($columns, $name) {
                $table->index($columns, $name);
            });
        }

        if (Schema::hasTable('agent_command_logs') && Schema::hasColumn('agent_command_logs', 'operating_company_id')) {
            Schema::table('agent_command_logs', function (Blueprint $table) {
                $table->index(['operating_company_id', 'created_at'], 'agent_logs_oc_created_idx');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($name) {
                $table->dropIndex($name);
            });
        }

        if (Schema::hasTable('agent_command_logs')) {
            Schema::table('agent_command_logs', function (Blueprint $table) {
                $table->dropIndex('agent_logs_oc_created_idx');
            });
        }
    }
};
