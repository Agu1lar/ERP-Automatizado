<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('valor_frete_entrega', 12, 2)->nullable()->after('local_obra');
            $table->decimal('valor_frete_recolhida', 12, 2)->nullable()->after('valor_frete_entrega');
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->decimal('valor_contratado', 12, 2)->nullable()->after('valor_locacao');
            $table->decimal('horimetro_entrada', 12, 2)->nullable()->after('local_entrega');
            $table->decimal('horimetro_saida', 12, 2)->nullable()->after('horimetro_entrada');
        });

        Schema::table('rental_asset_substitutions', function (Blueprint $table) {
            $table->decimal('horimetro_saida', 12, 2)->nullable()->after('motivo');
            $table->decimal('horimetro_entrada', 12, 2)->nullable()->after('horimetro_saida');
        });
    }

    public function down(): void
    {
        Schema::table('rental_asset_substitutions', function (Blueprint $table) {
            $table->dropColumn(['horimetro_saida', 'horimetro_entrada']);
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn(['valor_contratado', 'horimetro_entrada', 'horimetro_saida']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['valor_frete_entrega', 'valor_frete_recolhida']);
        });
    }
};
