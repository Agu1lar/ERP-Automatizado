<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidade');
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->string('acao');
            $table->json('antes_json')->nullable();
            $table->json('depois_json')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['entidade', 'entidade_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
