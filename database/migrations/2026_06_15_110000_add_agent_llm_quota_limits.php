<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('agent_daily_token_limit')->nullable()->after('ativo');
        });

        Schema::table('operating_companies', function (Blueprint $table) {
            $table->unsignedInteger('agent_daily_token_limit')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agent_daily_token_limit');
        });

        Schema::table('operating_companies', function (Blueprint $table) {
            $table->dropColumn('agent_daily_token_limit');
        });
    }
};
