<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('voltagem', 50)->nullable()->after('serie');
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('rental_id')->constrained()->nullOnDelete();
            $table->text('parecer_tecnico')->nullable()->after('solucao_aplicada');
            $table->string('assinatura_caixa')->nullable()->after('parecer_tecnico');
            $table->string('assinatura_orcado_por')->nullable()->after('assinatura_caixa');
            $table->string('assinatura_montado_por')->nullable()->after('assinatura_orcado_por');
        });

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->string('codigo_alternativo')->nullable()->after('codigo_peca');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->dropColumn('codigo_alternativo');
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn([
                'parecer_tecnico',
                'assinatura_caixa',
                'assinatura_orcado_por',
                'assinatura_montado_por',
            ]);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('voltagem');
        });
    }
};
