<?php

use App\Support\RentalActiveIndex;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RentalActiveIndex::recreate();
    }

    public function down(): void
    {
        // Mantido pelo migration 2026_06_10_240000.
    }
};
