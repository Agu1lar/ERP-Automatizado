<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('valor_caucao', 12, 2)->nullable()->after('valor_faturamento');
            $table->string('caucao_status', 20)->default('nao_aplicavel')->after('valor_caucao');
            $table->timestamp('caucao_recebida_at')->nullable()->after('caucao_status');
            $table->timestamp('caucao_devolvida_at')->nullable()->after('caucao_recebida_at');
            $table->text('caucao_observacoes')->nullable()->after('caucao_devolvida_at');
        });

        Schema::create('rental_asset_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_asset_id')->constrained('assets');
            $table->foreignId('to_asset_id')->constrained('assets');
            $table->text('motivo')->nullable();
            $table->foreignId('substituted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('substituted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_asset_substitutions');

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'valor_caucao',
                'caucao_status',
                'caucao_recebida_at',
                'caucao_devolvida_at',
                'caucao_observacoes',
            ]);
        });
    }
};
