<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('tipo', 20)->default('externa');
            $table->string('telefone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('endereco')->nullable();
            $table->string('contato_principal')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nome');
            $table->index('tipo');
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cpf', 11)->unique();
            $table->date('data_nascimento')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('telefone_secundario', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('cargo')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('endereco_residencial')->nullable();
            $table->text('endereco_comercial')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nome');
            $table->index('telefone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
        Schema::dropIfExists('companies');
    }
};
