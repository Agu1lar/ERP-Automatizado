<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('codigo')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_purchase_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('origem', 30);
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->string('status', 20)->default('aberto');
            $table->string('forma_pagamento', 30)->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->foreignId('pago_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->text('observacoes_pagamento')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'vencimento']);
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignId('external_company_id')->nullable()->after('customer_id')->constrained('companies')->nullOnDelete();
            $table->decimal('valor_servico_externo', 12, 2)->nullable()->after('external_company_id');
            $table->foreignId('payable_title_id')->nullable()->after('receivable_title_id')->constrained('payable_titles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payable_title_id');
            $table->dropConstrainedForeignId('external_company_id');
            $table->dropColumn('valor_servico_externo');
        });

        Schema::dropIfExists('payable_titles');
    }
};
