<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('valor_faturamento', 12, 2)->nullable()->after('ficha_descricao');
        });

        Schema::create('preventive_maintenance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_model_id')->constrained()->cascadeOnDelete();
            $table->decimal('interval_horas', 10, 2);
            $table->string('descricao');
            $table->boolean('ativo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_model_id', 'ativo']);
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignId('preventive_rule_id')->nullable()->after('customer_id')
                ->constrained('preventive_maintenance_rules')->nullOnDelete();
            $table->decimal('horimetro_servico', 10, 2)->nullable()->after('preventive_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preventive_rule_id');
            $table->dropColumn('horimetro_servico');
        });

        Schema::dropIfExists('preventive_maintenance_rules');

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('valor_faturamento');
        });
    }
};
