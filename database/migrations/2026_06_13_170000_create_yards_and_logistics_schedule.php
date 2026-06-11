<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained('operating_companies')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cidade')->nullable();
            $table->string('endereco')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['operating_company_id', 'ativo']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('yard_id')->nullable()->after('equipment_model_id')->constrained('yards')->nullOnDelete();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->date('entrega_agendada_em')->nullable()->after('valor_frete_recolhida');
            $table->string('entrega_turno', 20)->nullable()->after('entrega_agendada_em');
            $table->text('entrega_observacoes')->nullable()->after('entrega_turno');
            $table->date('retirada_agendada_em')->nullable()->after('entrega_observacoes');
            $table->string('retirada_turno', 20)->nullable()->after('retirada_agendada_em');
            $table->text('retirada_observacoes')->nullable()->after('retirada_turno');

            $table->index(['operating_company_id', 'entrega_agendada_em']);
            $table->index(['operating_company_id', 'retirada_agendada_em']);
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['operating_company_id', 'retirada_agendada_em']);
            $table->dropIndex(['operating_company_id', 'entrega_agendada_em']);
            $table->dropColumn([
                'entrega_agendada_em',
                'entrega_turno',
                'entrega_observacoes',
                'retirada_agendada_em',
                'retirada_turno',
                'retirada_observacoes',
            ]);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('yard_id');
        });

        Schema::dropIfExists('yards');
    }
};
