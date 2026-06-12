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

        DB::statement('DROP INDEX IF EXISTS rentals_one_active_per_asset');
        DB::statement('DROP INDEX IF EXISTS rentals_one_occupied_per_asset');
        DB::statement('DROP INDEX IF EXISTS rentals_one_immediate_reserve_per_asset');

        if (Schema::hasColumn('rentals', 'scheduled_start_at')) {
            self::createFutureAwareIndexes();

            return;
        }

        $activeStatuses = implode("','", [
            RentalStatus::Reservado->value,
            RentalStatus::Locado->value,
            RentalStatus::EmInspecao->value,
        ]);

        DB::statement(
            "CREATE UNIQUE INDEX rentals_one_active_per_asset ON rentals (asset_id) WHERE status IN ('{$activeStatuses}')"
        );
    }

    private static function createFutureAwareIndexes(): void
    {
        $occupiedStatuses = implode("','", [
            RentalStatus::Locado->value,
            RentalStatus::EmInspecao->value,
        ]);

        DB::statement(
            "CREATE UNIQUE INDEX rentals_one_occupied_per_asset ON rentals (asset_id) WHERE status IN ('{$occupiedStatuses}')"
        );

        DB::statement(
            "CREATE UNIQUE INDEX rentals_one_immediate_reserve_per_asset ON rentals (asset_id) WHERE status = '"
            .RentalStatus::Reservado->value
            ."' AND scheduled_start_at IS NULL"
        );
    }
}
