<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->string('codigo')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('rascunho');
            $table->date('pedido_em')->nullable();
            $table->date('recebido_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'pedido_em']);
        });

        Schema::create('part_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_catalog_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantidade_pedida', 12, 2);
            $table->decimal('quantidade_recebida', 12, 2)->default(0);
            $table->decimal('valor_unitario', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('part_catalog_supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('valor_unitario', 12, 2);
            $table->foreignId('part_purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observacao')->nullable();
            $table->timestamps();

            $table->index(['part_catalog_item_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_catalog_supplier_prices');
        Schema::dropIfExists('part_purchase_order_items');
        Schema::dropIfExists('part_purchase_orders');
    }
};
