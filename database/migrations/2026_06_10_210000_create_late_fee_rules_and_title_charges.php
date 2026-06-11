<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('escopo', 20);
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('nome')->nullable();
            $table->decimal('multa_percent', 8, 4)->default(0);
            $table->decimal('juros_mensal_percent', 8, 4)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['escopo', 'ativo']);
        });

        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->decimal('multa_percent_aplicada', 8, 4)->nullable()->after('observacoes_pagamento');
            $table->decimal('juros_mensal_percent_aplicada', 8, 4)->nullable()->after('multa_percent_aplicada');
            $table->decimal('multa_valor', 12, 2)->nullable()->after('juros_mensal_percent_aplicada');
            $table->decimal('juros_valor', 12, 2)->nullable()->after('multa_valor');
            $table->decimal('valor_total_com_encargos', 12, 2)->nullable()->after('juros_valor');
            $table->timestamp('encargos_aplicados_em')->nullable()->after('valor_total_com_encargos');
            $table->foreignId('encargos_aplicados_por')->nullable()->after('encargos_aplicados_em')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encargos_aplicados_por');
            $table->dropColumn([
                'multa_percent_aplicada',
                'juros_mensal_percent_aplicada',
                'multa_valor',
                'juros_valor',
                'valor_total_com_encargos',
                'encargos_aplicados_em',
            ]);
        });

        Schema::dropIfExists('late_fee_rules');
    }
};
