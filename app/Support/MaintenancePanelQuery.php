<?php

namespace App\Support;

use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MaintenancePanelQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(array $filters): Builder
    {
        $query = MaintenanceOrder::query()
            ->with(['asset.equipmentModel.category', 'assignedToUser', 'customer', 'rental.customer'])
            ->open();

        if (filled($filters['search'] ?? null)) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('codigo', 'like', $term)
                    ->orWhere('descricao_problema', 'like', $term)
                    ->orWhereHas('asset', fn (Builder $assetQuery) => $assetQuery->where('codigo_patrimonio', 'like', $term));
            });
        }

        if (filled($filters['category_id'] ?? null)) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('asset.equipmentModel', fn (Builder $modelQuery) => $modelQuery->where('equipment_category_id', $categoryId));
        }

        if (filled($filters['assigned_to'] ?? null)) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (filled($filters['prioridade'] ?? null)) {
            $query->where('prioridade', $filters['prioridade']);
        }

        if (filled($filters['tipo'] ?? null)) {
            $query->where('tipo', $filters['tipo']);
        }

        if (! empty($filters['overdue_only'])) {
            $query->overdue();
        }

        return $query->orderByRaw('expected_completion_at IS NULL')
            ->orderBy('expected_completion_at')
            ->orderByDesc('opened_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection<int, MaintenanceOrder>>
     */
    public function boardColumns(array $filters): array
    {
        $orders = $this->apply($filters)->get();

        return [
            MaintenanceOrderStatus::Aberta->value => $orders->where('status', MaintenanceOrderStatus::Aberta->value)->values(),
            MaintenanceOrderStatus::EmExecucao->value => $orders->where('status', MaintenanceOrderStatus::EmExecucao->value)->values(),
            MaintenanceOrderStatus::AguardandoPeca->value => $orders->where('status', MaintenanceOrderStatus::AguardandoPeca->value)->values(),
            'atrasadas' => $orders->filter(fn (MaintenanceOrder $order) => $order->expected_completion_at
                && $order->expected_completion_at->lt(now()->startOfDay()))->values(),
        ];
    }
}
