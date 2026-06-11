<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_company_id')->nullable()->constrained('operating_companies')->nullOnDelete();
            $table->string('codigo', 20)->unique();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('status', 20)->default('rascunho');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->date('expected_return_at')->nullable();
            $table->string('local_obra')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('pricing_period')->nullable();
            $table->decimal('valor_estimado', 12, 2)->nullable();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_quotes');
    }
};
