<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_category_id')->constrained('equipment_categories')->cascadeOnDelete();
            $table->string('marca');
            $table->string('modelo');
            $table->json('especificacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marca', 'modelo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_models');
    }
};
