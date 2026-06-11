<?php

namespace App\Livewire\Dashboard;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\AssetStatusHistory;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Services\PreventiveMaintenanceService;
use App\Support\ActiveOperatingCompany;
use App\Support\DelinquencyReportQuery;
use App\Support\FichaCompleteness;
use App\Support\FinanceDashboardQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DashboardIndex extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', Asset::class);
    }

    public function render(): View
    {
        $statusCounts = Asset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $blockedAssets = Asset::query()
            ->with('equipmentModel.category')
            ->where('status', AssetStatus::Bloqueado->value)
            ->latest()
            ->limit(10)
            ->get();

        $recentChanges = AssetStatusHistory::query()
            ->with(['asset.equipmentModel', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingCheckouts = Rental::query()
            ->with(['asset.equipmentModel', 'customer'])
            ->pendingCheckout()
            ->latest('reserved_at')
            ->limit(10)
            ->get();

        $dueReturns = Rental::query()
            ->with(['asset.equipmentModel', 'customer'])
            ->dueToday()
            ->orderBy('expected_return_at')
            ->limit(10)
            ->get();

        $overdueReturns = Rental::query()
            ->with(['asset.equipmentModel', 'customer'])
            ->overdueReturns()
            ->orderBy('expected_return_at')
            ->limit(10)
            ->get();

        $overdueReturnsCount = Rental::query()->overdueReturns()->count();

        $overdueOrders = MaintenanceOrder::query()
            ->with(['asset.equipmentModel', 'assignedToUser'])
            ->overdue()
            ->orderBy('expected_completion_at')
            ->limit(10)
            ->get();

        $user = auth()->user();
        $showAnalytics = $user->can('dashboard.analytics');

        $rentalCounts = $showAnalytics
            ? Rental::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $maintenanceCounts = $showAnalytics
            ? MaintenanceOrder::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $fleetTotal = max(1, (int) $statusCounts->sum());

        $preventiveDue = $showAnalytics
            ? app(PreventiveMaintenanceService::class)->dueAssets()
            : [];

        $incompleteFichasCount = $showAnalytics
            ? Asset::query()->get()->filter(fn (Asset $a) => ! FichaCompleteness::isAssetComplete($a))->count()
            : 0;

        $financeSummary = $user->can('finance.view')
            ? app(DelinquencyReportQuery::class)->summary()
            : null;

        $financeDashboard = $user->can('finance.view')
            ? app(FinanceDashboardQuery::class)
            : null;

        $receivableWeek = $financeDashboard?->receivableThisWeekSummary();
        $receivableWeekTitles = $financeDashboard?->receivableThisWeekTitles() ?? collect();
        $billingCycleDueCount = $financeDashboard?->billingCycleDueCount() ?? 0;
        $billingCycleDueRentals = $financeDashboard?->billingCycleDueRentals() ?? collect();
        $pendingRenewalQueueCount = $financeDashboard?->pendingRenewalQueueCount() ?? 0;

        return view('livewire.dashboard.dashboard-index', [
            'statusCounts' => $statusCounts,
            'blockedAssets' => $blockedAssets,
            'recentChanges' => $recentChanges,
            'pendingCheckouts' => $pendingCheckouts,
            'dueReturns' => $dueReturns,
            'overdueReturns' => $overdueReturns,
            'overdueReturnsCount' => $overdueReturnsCount,
            'overdueOrders' => $overdueOrders,
            'showAnalytics' => $showAnalytics,
            'rentalCounts' => $rentalCounts,
            'maintenanceCounts' => $maintenanceCounts,
            'fleetTotal' => $fleetTotal,
            'statusLabels' => collect(AssetStatus::cases())->mapWithKeys(
                fn (AssetStatus $s) => [$s->value => $s->label()]
            ),
            'rentalLabels' => collect(RentalStatus::cases())->mapWithKeys(
                fn (RentalStatus $s) => [$s->value => $s->label()]
            ),
            'maintenanceLabels' => collect(MaintenanceOrderStatus::cases())->mapWithKeys(
                fn (MaintenanceOrderStatus $s) => [$s->value => $s->label()]
            ),
            'preventiveDue' => $preventiveDue,
            'preventiveDueCount' => count($preventiveDue),
            'incompleteFichasCount' => $incompleteFichasCount,
            'financeSummary' => $financeSummary,
            'receivableWeek' => $receivableWeek,
            'receivableWeekTitles' => $receivableWeekTitles,
            'billingCycleDueCount' => $billingCycleDueCount,
            'billingCycleDueRentals' => $billingCycleDueRentals,
            'pendingRenewalQueueCount' => $pendingRenewalQueueCount,
            'activeCompany' => ActiveOperatingCompany::current(),
        ]);
    }
}
