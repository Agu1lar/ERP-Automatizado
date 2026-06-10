<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_patrimonio')->unique();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->restrictOnDelete();
            $table->string('serie')->nullable();
            $table->decimal('valor_compra', 12, 2)->nullable();
            $table->date('data_compra')->nullable();
            $table->string('status')->default('disponivel');
            $table->string('localizacao')->nullable();
            $table->text('observacoes')->nullable();
            $table->text('motivo_bloqueio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('serie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
