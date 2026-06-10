<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('qr_code_path')->nullable()->after('motivo_bloqueio');
            $table->string('qr_code_status')->default('pending')->after('qr_code_path');
            $table->timestamp('qr_code_generated_at')->nullable()->after('qr_code_status');
            $table->text('qr_code_error')->nullable()->after('qr_code_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['qr_code_path', 'qr_code_status', 'qr_code_generated_at', 'qr_code_error']);
        });
    }
};
