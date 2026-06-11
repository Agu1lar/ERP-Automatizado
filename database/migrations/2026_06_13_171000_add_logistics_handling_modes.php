<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('entrega_modalidade', 30)->default('empresa_entrega')->after('valor_frete_recolhida');
            $table->string('retirada_modalidade', 30)->default('empresa_recolhe')->after('retirada_observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['entrega_modalidade', 'retirada_modalidade']);
        });
    }
};
