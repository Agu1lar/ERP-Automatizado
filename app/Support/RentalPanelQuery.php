<?php

namespace App\Support;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RentalPanelQuery
{
    /** @param array<string, mixed> $filters */
    public function apply(array $filters): Builder
    {
        $query = Rental::query()
            ->with(['asset.equipmentModel.category', 'customer']);

        $customerId = filled($filters['customer_id'] ?? null) ? (int) $filters['customer_id'] : null;
        $showHistory = (bool) ($filters['show_customer_history'] ?? false);

        if ($showHistory && $customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $status = $filters['status_scope'] ?? 'locado';

            if ($status === 'ativos') {
                $query->whereIn('status', [
                    RentalStatus::Reservado->value,
                    RentalStatus::Locado->value,
                    RentalStatus::EmInspecao->value,
                ]);
            } elseif ($status === 'locado') {
                $query->where('status', RentalStatus::Locado->value);
            } elseif (filled($status) && $status !== 'todos') {
                $query->where('status', $status);
            }

            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
        }

        if (filled($filters['category_id'] ?? null)) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('asset.equipmentModel', fn (Builder $modelQuery) => $modelQuery->where('equipment_category_id', $categoryId));
        }

        if (filled($filters['search'] ?? null)) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('codigo', 'like', $term)
                    ->orWhereHas('asset', fn (Builder $assetQuery) => $assetQuery->where('codigo_patrimonio', 'like', $term))
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->where('nome', 'like', $term));
            });
        }

        if (($filters['valor_min'] ?? '') !== '' && $filters['valor_min'] !== null) {
            $query->where('valor_faturamento', '>=', (float) $filters['valor_min']);
        }

        if (($filters['valor_max'] ?? '') !== '' && $filters['valor_max'] !== null) {
            $query->where('valor_faturamento', '<=', (float) $filters['valor_max']);
        }

        if (! empty($filters['overdue_only'])) {
            $query->overdueReturns();
        }

        return $this->applySort($query, (string) ($filters['sort_by'] ?? 'retorno'), (string) ($filters['sort_dir'] ?? 'asc'));
    }

    public function applySort(Builder $query, string $sortBy, string $sortDir): Builder
    {
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        return match ($sortBy) {
            'saida' => $query->orderByRaw('checkout_at IS NULL')->orderBy('checkout_at', $sortDir),
            'valor' => $query->orderByRaw('valor_faturamento IS NULL')->orderBy('valor_faturamento', $sortDir),
            'cliente' => $query
                ->join('customers', 'rentals.customer_id', '=', 'customers.id')
                ->select('rentals.*')
                ->orderBy('customers.nome', $sortDir),
            'categoria' => $query
                ->join('assets', 'rentals.asset_id', '=', 'assets.id')
                ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')
                ->join('equipment_categories', 'equipment_models.equipment_category_id', '=', 'equipment_categories.id')
                ->select('rentals.*')
                ->orderBy('equipment_categories.nome', $sortDir)
                ->orderBy('equipment_models.marca', $sortDir),
            'codigo' => $query->orderBy('codigo', $sortDir),
            'conclusao' => $query->orderByRaw('completed_at IS NULL')->orderBy('completed_at', $sortDir),
            default => $query->orderByRaw('expected_return_at IS NULL')->orderBy('expected_return_at', $sortDir),
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function sortOptions(): array
    {
        return [
            ['value' => 'retorno', 'label' => 'Previsão de retorno'],
            ['value' => 'saida', 'label' => 'Data de saída'],
            ['value' => 'valor', 'label' => 'Valor de faturamento'],
            ['value' => 'cliente', 'label' => 'Cliente'],
            ['value' => 'categoria', 'label' => 'Categoria do equipamento'],
            ['value' => 'codigo', 'label' => 'Código da locação'],
            ['value' => 'conclusao', 'label' => 'Data de conclusão'],
        ];
    }

  /** @return \Illuminate\Support\Collection<string, int> */
    public function summaryForCustomer(int $customerId): Collection
    {
        return Rental::query()
            ->where('customer_id', $customerId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count);
    }
}
