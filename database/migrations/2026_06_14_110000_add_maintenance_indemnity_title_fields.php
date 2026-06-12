<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->decimal('valor_indenizacao', 12, 2)->nullable()->after('impeditiva');
            $table->foreignId('receivable_title_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('receivable_titles')
                ->nullOnDelete();
        });

        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->foreignId('maintenance_order_id')
                ->nullable()
                ->after('rental_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_order_id');
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receivable_title_id');
            $table->dropColumn('valor_indenizacao');
        });
    }
};
