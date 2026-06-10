<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('tipo')->default('corretiva');
            $table->string('prioridade')->default('normal');
            $table->boolean('impeditiva')->default(true);
            $table->text('descricao_problema');
            $table->text('diagnostico')->nullable();
            $table->text('solucao_aplicada')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->date('expected_completion_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('expected_completion_at');
        });

        Schema::create('maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->string('descricao');
            $table->string('codigo_peca')->nullable();
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_unitario', 12, 2)->nullable();
            $table->string('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_labor_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('data');
            $table->decimal('horas', 6, 2);
            $table->string('descricao_atividade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_labor_hours');
        Schema::dropIfExists('maintenance_parts');
        Schema::dropIfExists('maintenance_orders');
    }
};
