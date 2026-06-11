<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_cycle_days')->default(28)->after('valor_calculado');
            $table->decimal('billing_min_amount', 12, 2)->nullable()->after('billing_cycle_days');
            $table->date('billing_period_start')->nullable()->after('billing_min_amount');
            $table->date('billing_period_end')->nullable()->after('billing_period_start');
            $table->date('last_billed_at')->nullable()->after('billing_period_end');
            $table->date('next_billing_at')->nullable()->after('last_billed_at');
        });

        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained();
            $table->string('descricao');
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->decimal('valor_locacao', 12, 2)->default(0);
            $table->decimal('valor_indenizacao', 12, 2)->nullable();
            $table->boolean('devolvido')->default(false);
            $table->timestamp('devolvido_em')->nullable();
            $table->string('local_entrega')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['rental_id', 'ativo']);
        });

        Schema::create('rental_billing_queue', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->string('tipo', 30);
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->decimal('valor_nf', 12, 2);
            $table->decimal('valor_car', 12, 2);
            $table->string('status', 20)->default('pendente');
            $table->timestamp('gerado_em');
            $table->timestamp('autorizado_em')->nullable();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('faturado_em')->nullable();
            $table->foreignId('faturado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receivable_title_id')->nullable()->constrained('receivable_titles')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['status', 'gerado_em']);
            $table->index(['rental_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_billing_queue');
        Schema::dropIfExists('rental_items');
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'billing_cycle_days',
                'billing_min_amount',
                'billing_period_start',
                'billing_period_end',
                'last_billed_at',
                'next_billing_at',
            ]);
        });
    }
};
