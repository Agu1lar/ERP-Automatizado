<?php

namespace App\Livewire\Reports;

use App\Services\CommercialReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CommercialReportIndex extends Component
{
    use AuthorizesRequests;

    public string $date_from = '';

    public string $date_to = '';

    public string $group_by = 'model';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.analytics'), 403);
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->toDateString();
    }

    public function render(): View
    {
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->endOfDay();

        $service = app(CommercialReportService::class);

        $rows = $this->group_by === 'user'
            ? $service->revenueByCommercialUser($from, $to)
            : $service->revenueByEquipmentType($from, $to, $this->group_by);

        return view('livewire.reports.commercial-report-index', [
            'rows' => $rows,
            'totalRevenue' => $service->totalRevenueInPeriod($from, $to),
        ]);
    }
}
