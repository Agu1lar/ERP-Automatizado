<?php

namespace App\Console\Commands;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use App\Services\RentalWorksiteGeocodingService;
use Illuminate\Console\Command;

class GeocodeRentalWorksites extends Command
{
    protected $signature = 'rentals:geocode-worksites {--status=locado : Status das locações} {--limit=100 : Máximo por execução} {--force : Regeocodificar mesmo com coordenadas}';

    protected $description = 'Geocodifica local da obra das locações (Nominatim/Google com cache)';

    public function handle(RentalWorksiteGeocodingService $geocoder): int
    {
        if (! config('geocoding.enabled', true)) {
            $this->warn('Geocoding desabilitado (GEOCODING_ENABLED=false).');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $query = Rental::query()
            ->where('status', $status)
            ->whereNotNull('local_obra')
            ->where('local_obra', '!=', '');

        if (! $force) {
            $query->whereNull('obra_latitude');
        }

        $rentals = $query->orderBy('id')->limit($limit)->get();
        $ok = 0;
        $fail = 0;

        foreach ($rentals as $rental) {
            if ($geocoder->geocodeAndStore($rental)) {
                $ok++;
                $this->line("✓ {$rental->codigo}");
            } else {
                $fail++;
                $this->line("✗ {$rental->codigo}");
            }

            usleep(1_100_000);
        }

        $this->info("Concluído: {$ok} geocodificada(s), {$fail} sem resultado.");

        return self::SUCCESS;
    }
}
