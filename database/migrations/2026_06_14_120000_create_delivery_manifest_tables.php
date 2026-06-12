<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('cnh', 20)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['operating_company_id', 'ativo']);
        });

        Schema::create('delivery_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->string('placa', 15);
            $table->string('descricao');
            $table->string('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['operating_company_id', 'placa']);
        });

        Schema::create('delivery_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->constrained()->cascadeOnDelete();
            $table->string('codigo')->unique();
            $table->date('data');
            $table->foreignId('delivery_driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('rascunho');
            $table->text('observacoes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['operating_company_id', 'data']);
            $table->index(['data', 'status']);
        });

        Schema::create('delivery_manifest_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequencia')->default(1);
            $table->string('tipo');
            $table->string('status')->default('pendente');
            $table->string('endereco')->nullable();
            $table->string('turno', 20)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['delivery_manifest_id', 'rental_id', 'tipo']);
            $table->index(['delivery_manifest_id', 'sequencia']);
        });

        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_manifest_stop_id')->constrained()->cascadeOnDelete();
            $table->string('receptor_nome');
            $table->longText('assinatura_imagem')->nullable();
            $table->string('foto_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('registrado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
        Schema::dropIfExists('delivery_manifest_stops');
        Schema::dropIfExists('delivery_manifests');
        Schema::dropIfExists('delivery_vehicles');
        Schema::dropIfExists('delivery_drivers');
    }
};
