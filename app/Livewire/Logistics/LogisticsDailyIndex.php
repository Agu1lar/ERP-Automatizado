<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Rental\Rental;
use App\Services\DeliveryManifestService;
use App\Support\LogisticsDailyQuery;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class LogisticsDailyIndex extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'data')]
    public string $selectedDate = '';

    #[Url(as: 'regiao')]
    public string $regionFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rental::class);

        if ($this->selectedDate === '') {
            $this->selectedDate = now()->toDateString();
        }
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->toDateString();
    }

    public function goToday(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function openManifest(DeliveryManifestService $service): void
    {
        $this->authorize('create', DeliveryManifest::class);

        $date = Carbon::parse($this->selectedDate);

        try {
            $manifest = $service->findOrGenerateForDate($date);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->redirectRoute('logistics.manifest.show', $manifest, navigate: true);
    }

    public function render(LogisticsDailyQuery $query): View
    {
        $date = Carbon::parse($this->selectedDate);
        $manifest = DeliveryManifest::query()
            ->whereDate('data', $date->toDateString())
            ->where('status', '!=', \App\Enums\DeliveryManifestStatus::Cancelado->value)
            ->first();

        $region = $this->regionFilter !== '' ? $this->regionFilter : null;

        return view('livewire.logistics.logistics-daily-index', [
            'date' => $date,
            'counts' => $query->countsForDate($date, $region),
            'deliveries' => $query->scheduledDeliveries($date, $region),
            'customerPickups' => $query->customerPickupsAtYard($date, $region),
            'pickups' => $query->scheduledPickups($date, $region),
            'customerReturns' => $query->customerReturnsAtYard($date, $region),
            'expectedReturns' => $query->expectedReturnsWithoutPickupSchedule($date, $region),
            'manifest' => $manifest,
            'canManageManifest' => auth()->user()->can('create', DeliveryManifest::class),
            'regionOptions' => \App\Enums\GeographicRegion::cases(),
        ]);
    }
}
