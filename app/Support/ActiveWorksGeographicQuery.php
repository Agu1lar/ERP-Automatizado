<?php

namespace App\Support;

use App\Enums\GeographicRegion;
use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use Illuminate\Support\Collection;

class ActiveWorksGeographicQuery
{
    /** @return Collection<int, Rental> */
    public function onSiteRentals(?string $region = null): Collection
    {
        return Rental::query()
            ->with([
                'customer',
                'asset.equipmentModel',
            ])
            ->where('status', RentalStatus::Locado->value)
            ->inGeographicRegion($region)
            ->orderByRaw("CASE regiao_geografica WHEN 'bh' THEN 1 WHEN 'rmbh' THEN 2 WHEN 'interior' THEN 3 ELSE 4 END")
            ->orderBy('local_obra')
            ->orderBy('codigo')
            ->get();
    }

    /** @return array<string, int> */
    public function countsByRegion(?string $region = null): array
    {
        $counts = array_fill_keys(array_map(fn (GeographicRegion $r) => $r->value, GeographicRegion::cases()), 0);

        Rental::query()
            ->where('status', RentalStatus::Locado->value)
            ->inGeographicRegion($region)
            ->selectRaw('COALESCE(regiao_geografica, ?) as regiao, COUNT(*) as total', [GeographicRegion::Indefinido->value])
            ->groupBy('regiao')
            ->pluck('total', 'regiao')
            ->each(function ($total, $regiao) use (&$counts) {
                if (isset($counts[$regiao])) {
                    $counts[$regiao] = (int) $total;
                }
            });

        return $counts;
    }

    /** @return Collection<int, Rental> */
    public function withoutWorksiteAddress(?string $region = null): Collection
    {
        return $this->onSiteRentals($region)
            ->filter(fn (Rental $rental) => blank($rental->local_obra))
            ->values();
    }

    /** @return Collection<int, Rental> */
    public function withWorksiteAddress(?string $region = null): Collection
    {
        return $this->onSiteRentals($region)
            ->filter(fn (Rental $rental) => filled($rental->local_obra))
            ->values();
    }
}
