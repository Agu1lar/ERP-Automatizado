<?php

namespace App\Livewire\Reports;

use App\Services\MaintenanceCostReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MaintenanceCostReportIndex extends Component
{
    use AuthorizesRequests;

    public string $date_from = '';

    public string $date_to = '';

    public string $tab = 'patrimonio';

    public string $region_filter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.analytics'), 403);
        $this->date_from = now()->subDays(90)->toDateString();
        $this->date_to = now()->toDateString();
    }

    public function render(): View
    {
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->endOfDay();
        $region = $this->region_filter !== '' ? $this->region_filter : null;
        $service = app(MaintenanceCostReportService::class);

        return view('livewire.reports.maintenance-cost-report-index', [
            'summary' => $service->summary($from, $to, $region),
            'assetRows' => $service->byAsset($from, $to, 100, $region),
            'categoryRows' => $service->byCategory($from, $to, $region),
            'orderRows' => $service->orderRows($from, $to),
            'regionOptions' => \App\Enums\GeographicRegion::cases(),
        ]);
    }
}
