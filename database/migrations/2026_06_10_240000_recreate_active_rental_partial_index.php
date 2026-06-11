<?php

use App\Support\RentalActiveIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        RentalActiveIndex::recreate();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rentals_one_active_per_asset');
    }
};
