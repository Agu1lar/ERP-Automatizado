<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->text('local_obra')->nullable()->after('ficha_descricao');
            $table->string('localizacao_origem')->nullable()->after('local_obra');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['local_obra', 'localizacao_origem']);
        });
    }
};
