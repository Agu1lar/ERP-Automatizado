<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Índice movido para 2026_06_10_240000 — alterações em rentals via Schema::table no SQLite
 * recriam o índice sem a cláusula WHERE se ele for criado antes dessas migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};