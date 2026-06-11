<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('limite_credito', 12, 2)->nullable()->after('ativo');
            $table->boolean('bloqueio_inadimplencia')->default(true)->after('limite_credito');
        });

        Schema::create('receivable_titles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('parcela')->default(1);
            $table->unsignedSmallInteger('total_parcelas')->default(1);
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->string('status', 20)->default('aberto');
            $table->string('forma_pagamento', 30)->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->foreignId('pago_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->text('observacoes_pagamento')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'vencimento']);
            $table->index('rental_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_titles');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['limite_credito', 'bloqueio_inadimplencia']);
        });
    }
};
