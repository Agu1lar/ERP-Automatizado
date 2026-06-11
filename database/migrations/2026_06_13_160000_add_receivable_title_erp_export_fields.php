<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->timestamp('exportado_erp_em')->nullable()->after('encargos_aplicados_por');
            $table->foreignId('exportado_erp_por')->nullable()->after('exportado_erp_em')->constrained('users')->nullOnDelete();
            $table->string('exportado_erp_formato', 32)->nullable()->after('exportado_erp_por');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exportado_erp_por');
            $table->dropColumn(['exportado_erp_em', 'exportado_erp_formato']);
        });
    }
};
