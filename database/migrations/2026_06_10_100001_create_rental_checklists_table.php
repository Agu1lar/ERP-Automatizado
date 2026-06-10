<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        Schema::create('rental_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('item_key');
            $table->string('item_label');
            $table->boolean('checked')->default(false);
            $table->string('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_checklist_items');
        Schema::dropIfExists('rental_checklists');
    }
};
