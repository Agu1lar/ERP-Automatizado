<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->text('descricao')->nullable()->after('serie');
            $table->decimal('horimetro', 12, 2)->nullable()->after('descricao');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('horimetro_saida', 12, 2)->nullable()->after('observacoes');
            $table->decimal('horimetro_retorno', 12, 2)->nullable()->after('horimetro_saida');
            $table->text('ficha_descricao')->nullable()->after('horimetro_retorno');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['descricao', 'horimetro']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['horimetro_saida', 'horimetro_retorno', 'ficha_descricao']);
        });
    }
};
