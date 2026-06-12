<?php

namespace App\Livewire\Reports;

use App\Services\FleetAnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FleetAnalyticsIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'ocupacao';

    public string $date_from = '';

    public string $date_to = '';

    public string $occupancy_group = 'asset';

    public string $calendar_month = '';

    public ?int $calendar_category_id = null;

    public ?int $calendar_model_id = null;

    public string $region_filter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.analytics'), 403);
        $this->date_from = now()->subDays(89)->toDateString();
        $this->date_to = now()->toDateString();
        $this->calendar_month = now()->format('Y-m');
    }

    public function previousCalendarMonth(): void
    {
        $this->calendar_month = Carbon::createFromFormat('Y-m', $this->calendar_month)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextCalendarMonth(): void
    {
        $this->calendar_month = Carbon::createFromFormat('Y-m', $this->calendar_month)
            ->addMonth()
            ->format('Y-m');
    }

    public function render(): View
    {
        $service = app(FleetAnalyticsService::class);
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->endOfDay();

        $region = $this->region_filter !== '' ? $this->region_filter : null;

        $occupancySummary = $service->occupancySummary($from, $to, $region);
        $occupancyRows = $service->occupancy($from, $to, $this->occupancy_group, $region);
        $profitabilityRows = $service->profitabilityByAsset($from, $to, 100, $region);
        $investmentRows = $service->investmentAnalysis($from, $to, 100, $region);
        $divestmentRows = $service->divestmentSuggestions($from, $to, $region);

        $calendar = $service->availabilityCalendar(
            Carbon::createFromFormat('Y-m', $this->calendar_month)->startOfMonth(),
            $this->calendar_category_id,
            $this->calendar_model_id,
        );

        return view('livewire.reports.fleet-analytics-index', [
            'occupancySummary' => $occupancySummary,
            'occupancyRows' => $occupancyRows,
            'profitabilityRows' => $profitabilityRows,
            'investmentRows' => $investmentRows,
            'divestmentRows' => $divestmentRows,
            'calendar' => $calendar,
            'categories' => $service->categoryOptions(),
            'models' => $service->modelOptions(),
            'regionOptions' => \App\Enums\GeographicRegion::cases(),
        ]);
    }
}
