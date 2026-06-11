<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('commercial_user_id')
                ->nullable()
                ->after('reserved_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('ativo')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('rentals')
            ->whereNotNull('reserved_by')
            ->update(['commercial_user_id' => DB::raw('reserved_by')]);
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commercial_user_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
