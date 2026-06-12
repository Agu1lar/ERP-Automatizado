<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Rental\Rental;
use App\Support\ActiveWorksGeographicQuery;
use App\Support\WorksiteMapLocator;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ActiveWorksMapIndex extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'regiao')]
    public string $regionFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rental::class);
    }

    public function render(
        ActiveWorksGeographicQuery $query,
        WorksiteMapLocator $locator,
    ): View {
        $region = $this->regionFilter !== '' ? $this->regionFilter : null;
        $onSite = $query->onSiteRentals($region);
        $withAddress = $query->withWorksiteAddress($region);
        $withoutAddress = $query->withoutWorksiteAddress($region);

        $markers = $withAddress->map(function (Rental $rental) use ($locator) {
            $position = $locator->locate($rental);

            return [
                'id' => $rental->id,
                'codigo' => $rental->codigo,
                'lat' => $position['lat'],
                'lng' => $position['lng'],
                'precision' => $position['precision'],
                'precision_label' => $position['precision_label'],
                'city' => $position['city'],
                'geocoded' => $rental->hasObraCoordinates(),
                'local_obra' => $rental->local_obra,
                'region' => $rental->regionEnum()->value,
                'region_label' => $rental->regionEnum()->shortLabel(),
                'customer' => $rental->customer?->nome ?? '—',
                'asset' => $rental->asset?->codigo_patrimonio ?? '—',
                'equipment' => $rental->asset?->equipmentDisplayName() ?? '—',
                'url' => route('rentals.show', $rental),
                'overdue' => $rental->isReturnOverdue(),
                'days_overdue' => $rental->daysOverdue(),
                'expected_return' => $rental->expected_return_at?->format('d/m/Y'),
            ];
        })->values()->all();

        $grouped = $withAddress->groupBy(fn (Rental $rental) => $rental->regionEnum()->value);

        return view('livewire.logistics.active-works-map-index', [
            'countsByRegion' => $query->countsByRegion($region),
            'totalOnSite' => $onSite->count(),
            'markers' => $markers,
            'grouped' => $grouped,
            'withoutAddress' => $withoutAddress,
            'mapCenter' => $locator->defaultMapCenter(),
            'mapZoom' => $locator->defaultZoom(),
            'regionOptions' => \App\Enums\GeographicRegion::cases(),
        ]);
    }
}
