<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Rental\Rental;
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

    public function render(LogisticsDailyQuery $query): View
    {
        $date = Carbon::parse($this->selectedDate);

        return view('livewire.logistics.logistics-daily-index', [
            'date' => $date,
            'counts' => $query->countsForDate($date),
            'deliveries' => $query->scheduledDeliveries($date),
            'customerPickups' => $query->customerPickupsAtYard($date),
            'pickups' => $query->scheduledPickups($date),
            'customerReturns' => $query->customerReturnsAtYard($date),
            'expectedReturns' => $query->expectedReturnsWithoutPickupSchedule($date),
        ]);
    }
}
