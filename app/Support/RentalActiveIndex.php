<?php

namespace App\Support;

use App\Enums\RentalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RentalActiveIndex
{
    public static function recreate(): void
    {
        if (! Schema::hasTable('rentals')) {
            return;
        }

        $activeStatuses = implode("','", [
            RentalStatus::Reservado->value,
            RentalStatus::Locado->value,
            RentalStatus::EmInspecao->value,
        ]);

        DB::statement('DROP INDEX IF EXISTS rentals_one_active_per_asset');

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX rentals_one_active_per_asset ON rentals (asset_id) WHERE status IN ('{$activeStatuses}')"
            );

            return;
        }

        // SQLite: índice parcial — só uma locação ativa por patrimônio.
        DB::statement(
            "CREATE UNIQUE INDEX rentals_one_active_per_asset ON rentals (asset_id) WHERE status IN ('{$activeStatuses}')"
        );
    }
}
