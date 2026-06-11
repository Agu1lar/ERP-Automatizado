<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('bloqueado')->default(false)->after('bloqueio_inadimplencia');
            $table->text('motivo_bloqueio')->nullable()->after('bloqueado');
            $table->timestamp('bloqueado_at')->nullable()->after('motivo_bloqueio');
            $table->foreignId('bloqueado_by')->nullable()->after('bloqueado_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bloqueado_by');
            $table->dropColumn(['bloqueado', 'motivo_bloqueio', 'bloqueado_at']);
        });
    }
};
