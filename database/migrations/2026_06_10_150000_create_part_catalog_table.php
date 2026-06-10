<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_peca')->unique();
            $table->string('codigo_alternativo')->nullable();
            $table->string('descricao');
            $table->decimal('valor_unitario_padrao', 12, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('descricao');
            $table->index('codigo_alternativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_catalog_items');
    }
};
