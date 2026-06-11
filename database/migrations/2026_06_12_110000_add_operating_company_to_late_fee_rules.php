<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('late_fee_rules')) {
            return;
        }

        if (! Schema::hasColumn('late_fee_rules', 'operating_company_id')) {
            Schema::table('late_fee_rules', function (Blueprint $table) {
                $table->foreignId('operating_company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('operating_companies');
            });

            DB::table('late_fee_rules')->whereNull('operating_company_id')->update(['operating_company_id' => 1]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('late_fee_rules') || ! Schema::hasColumn('late_fee_rules', 'operating_company_id')) {
            return;
        }

        Schema::table('late_fee_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operating_company_id');
        });
    }
};
