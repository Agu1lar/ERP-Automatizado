<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_model_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('periodo', 20);
            $table->decimal('valor', 12, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['equipment_model_id', 'periodo']);
            $table->unique(['equipment_category_id', 'periodo']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('pricing_period', 20)->nullable()->after('valor_faturamento');
            $table->unsignedInteger('billed_days')->nullable()->after('pricing_period');
            $table->decimal('valor_calculado', 12, 2)->nullable()->after('billed_days');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['pricing_period', 'billed_days', 'valor_calculado']);
        });

        Schema::dropIfExists('equipment_pricings');
    }
};
