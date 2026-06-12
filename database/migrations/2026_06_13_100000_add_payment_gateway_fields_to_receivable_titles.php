<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->string('gateway_driver', 30)->nullable()->after('exportado_erp_formato');
            $table->string('gateway_charge_id', 80)->nullable()->after('gateway_driver');
            $table->string('gateway_status', 30)->nullable()->after('gateway_charge_id');
            $table->string('gateway_billing_type', 20)->nullable()->after('gateway_status');
            $table->text('pix_qr_code')->nullable()->after('gateway_billing_type');
            $table->text('pix_qr_image_url')->nullable()->after('pix_qr_code');
            $table->string('boleto_url', 500)->nullable()->after('pix_qr_image_url');
            $table->string('gateway_invoice_url', 500)->nullable()->after('boleto_url');
            $table->timestamp('gateway_charge_created_at')->nullable()->after('gateway_invoice_url');

            $table->index('gateway_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_titles', function (Blueprint $table) {
            $table->dropIndex(['gateway_charge_id']);
            $table->dropColumn([
                'gateway_driver',
                'gateway_charge_id',
                'gateway_status',
                'gateway_billing_type',
                'pix_qr_code',
                'pix_qr_image_url',
                'boleto_url',
                'gateway_invoice_url',
                'gateway_charge_created_at',
            ]);
        });
    }
};
