<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_categories', function (Blueprint $table) {
            $table->boolean('usa_horimetro')->default(true)->after('tipo_linha');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_categories', function (Blueprint $table) {
            $table->dropColumn('usa_horimetro');
        });
    }
};
