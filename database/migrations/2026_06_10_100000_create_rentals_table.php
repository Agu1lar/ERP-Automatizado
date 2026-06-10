<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('reserved_at');
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checkout_at')->nullable();
            $table->foreignId('checkout_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('expected_return_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
