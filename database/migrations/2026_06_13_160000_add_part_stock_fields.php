<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_catalog_items', function (Blueprint $table) {
            $table->decimal('estoque_atual', 12, 2)->default(0)->after('valor_unitario_padrao');
            $table->decimal('estoque_minimo', 12, 2)->nullable()->after('estoque_atual');
        });

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->foreignId('part_catalog_item_id')
                ->nullable()
                ->after('maintenance_order_id')
                ->constrained('part_catalog_items')
                ->nullOnDelete();
            $table->boolean('estoque_baixado')->default(false)->after('observacao');
        });

        Schema::create('part_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo');
            $table->decimal('quantidade', 12, 2);
            $table->decimal('saldo_anterior', 12, 2);
            $table->decimal('saldo_posterior', 12, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observacao')->nullable();
            $table->timestamps();

            $table->index(['part_catalog_item_id', 'created_at']);
            $table->index(['maintenance_order_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_stock_movements');

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_catalog_item_id');
            $table->dropColumn('estoque_baixado');
        });

        Schema::table('part_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['estoque_atual', 'estoque_minimo']);
        });
    }
};
