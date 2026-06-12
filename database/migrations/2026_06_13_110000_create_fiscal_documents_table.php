<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receivable_title_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_queue_entry_id')->nullable()->constrained('rental_billing_queue')->nullOnDelete();
            $table->string('codigo')->unique();
            $table->string('tipo', 30);
            $table->string('status', 30)->default('pendente');
            $table->decimal('valor', 12, 2);
            $table->string('descricao', 500);
            $table->string('erp_provider', 30)->default('omie');
            $table->string('erp_external_id', 120)->nullable();
            $table->json('erp_payload')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->timestamp('enviado_erp_em')->nullable();
            $table->foreignId('enviado_erp_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emitido_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'tipo']);
            $table->index('receivable_title_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
