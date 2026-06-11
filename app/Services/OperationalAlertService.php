<?php

namespace App\Services;

use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\ActiveOperatingCompany;
use Illuminate\Support\Collection;

class OperationalAlertService
{
    public function __construct(
        private readonly PreventiveMaintenanceService $preventiveMaintenanceService,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('operational_alerts.enabled', true);
    }

    /** @return Collection<int, Rental> */
    public function overdueReturns(int $limit = 25): Collection
    {
        return Rental::query()
            ->with(['customer', 'asset'])
            ->overdueReturns()
            ->orderBy('expected_return_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, MaintenanceOrder> */
    public function overdueOrders(int $limit = 25): Collection
    {
        return MaintenanceOrder::query()
            ->with(['asset', 'assignedToUser'])
            ->overdue()
            ->orderBy('expected_completion_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<array{asset: Asset, rule: PreventiveMaintenanceRule, horas_desde_ultima: float|null}>
     */
    public function preventiveDue(int $limit = 25): array
    {
        return array_slice(
            collect(ActiveOperatingCompany::forEach(
                fn () => $this->preventiveMaintenanceService->dueAssets()
            ))->flatten(1)->values()->all(),
            0,
            $limit,
        );
    }

    public function hasAnyAlert(): bool
    {
        return $this->overdueReturns(1)->isNotEmpty()
            || $this->overdueOrders(1)->isNotEmpty()
            || $this->preventiveDue(1) !== [];
    }

    /**
     * @return array{
     *     overdue_returns?: Collection<int, Rental>,
     *     overdue_orders?: Collection<int, MaintenanceOrder>,
     *     preventive_due?: list<array{asset: Asset, rule: PreventiveMaintenanceRule, horas_desde_ultima: float|null}>
     * }
     */
    public function sectionsForUser(User $user): array
    {
        $sections = [];
        $permissions = config('operational_alerts.permissions', []);

        if ($user->can($permissions['overdue_returns'] ?? 'rentals.view')) {
            $returns = $this->overdueReturns();
            if ($returns->isNotEmpty()) {
                $sections['overdue_returns'] = $returns;
            }
        }

        if ($user->can($permissions['overdue_orders'] ?? 'maintenance.view')) {
            $orders = $this->overdueOrders();
            if ($orders->isNotEmpty()) {
                $sections['overdue_orders'] = $orders;
            }
        }

        if ($user->can($permissions['preventive_due'] ?? 'maintenance.view')) {
            $preventive = $this->preventiveDue();
            if ($preventive !== []) {
                $sections['preventive_due'] = $preventive;
            }
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    public function allSections(): array
    {
        $sections = [];

        $returns = $this->overdueReturns();
        if ($returns->isNotEmpty()) {
            $sections['overdue_returns'] = $returns;
        }

        $orders = $this->overdueOrders();
        if ($orders->isNotEmpty()) {
            $sections['overdue_orders'] = $orders;
        }

        $preventive = $this->preventiveDue();
        if ($preventive !== []) {
            $sections['preventive_due'] = $preventive;
        }

        return $sections;
    }

    /** @return Collection<int, User> */
    public function notifiableUsers(): Collection
    {
        return User::query()
            ->where('ativo', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $this->sectionsForUser($user) !== []);
    }

    /** @return list<string> */
    public function extraRecipientEmails(): array
    {
        return config('operational_alerts.extra_recipients', []);
    }
}
