<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommercialReportService
{
    /**
     * Faturamento agrupado por tipo de equipamento (modelo ou categoria), não por patrimônio.
     *
     * @return Collection<int, object{grupo_id: int, grupo_nome: string, total_locacoes: int, faturamento_total: float, ticket_medio: float}>
     */
    public function revenueByEquipmentType(
        CarbonInterface $from,
        CarbonInterface $to,
        string $groupBy = 'model',
    ): Collection {
        $groupBy = $groupBy === 'category' ? 'category' : 'model';

        $query = Rental::query()
            ->join('assets', 'rentals.asset_id', '=', 'assets.id')
            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')
            ->join('equipment_categories', 'equipment_models.equipment_category_id', '=', 'equipment_categories.id')
            ->where('rentals.status', RentalStatus::Concluido->value)
            ->whereNotNull('rentals.completed_at')
            ->whereDate('rentals.completed_at', '>=', $from->toDateString())
            ->whereDate('rentals.completed_at', '<=', $to->toDateString());

        if ($groupBy === 'category') {
            $query->select([
                'equipment_categories.id as grupo_id',
                DB::raw('COUNT(rentals.id) as total_locacoes'),
                DB::raw('COALESCE(SUM(rentals.valor_faturamento), 0) as faturamento_total'),
            ])->groupBy('equipment_categories.id');
        } else {
            $query->select([
                'equipment_models.id as grupo_id',
                DB::raw('COUNT(rentals.id) as total_locacoes'),
                DB::raw('COALESCE(SUM(rentals.valor_faturamento), 0) as faturamento_total'),
            ])->groupBy('equipment_models.id');
        }

        $rows = $query->orderByDesc('faturamento_total')->get();

        $names = $groupBy === 'category'
            ? \App\Models\Domain\Fleet\EquipmentCategory::query()->whereIn('id', $rows->pluck('grupo_id'))->pluck('nome', 'id')
            : \App\Models\Domain\Fleet\EquipmentModel::query()->whereIn('id', $rows->pluck('grupo_id'))->get()->mapWithKeys(
                fn ($m) => [$m->id => $m->displayName()]
            );

        return $rows->map(function ($row) use ($names) {
            $row->grupo_nome = $names[$row->grupo_id] ?? '—';
            $row->faturamento_total = (float) $row->faturamento_total;
            $row->total_locacoes = (int) $row->total_locacoes;
            $row->ticket_medio = $row->total_locacoes > 0
                ? round($row->faturamento_total / $row->total_locacoes, 2)
                : 0.0;

            return $row;
        });
    }

    public function totalRevenueInPeriod(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Rental::query()
            ->where('status', RentalStatus::Concluido->value)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $from->toDateString())
            ->whereDate('completed_at', '<=', $to->toDateString())
            ->sum('valor_faturamento');
    }

    /**
     * Faturamento agrupado pelo responsável comercial da locação.
     *
     * @return Collection<int, object{grupo_id: int|null, grupo_nome: string, total_locacoes: int, faturamento_total: float, ticket_medio: float}>
     */
    public function revenueByCommercialUser(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = Rental::query()
            ->leftJoin('users', 'rentals.commercial_user_id', '=', 'users.id')
            ->where('rentals.status', RentalStatus::Concluido->value)
            ->whereNotNull('rentals.completed_at')
            ->whereDate('rentals.completed_at', '>=', $from->toDateString())
            ->whereDate('rentals.completed_at', '<=', $to->toDateString())
            ->select([
                'rentals.commercial_user_id as grupo_id',
                DB::raw("COALESCE(users.name, 'Sem responsável') as grupo_nome"),
                DB::raw('COUNT(rentals.id) as total_locacoes'),
                DB::raw('COALESCE(SUM(rentals.valor_faturamento), 0) as faturamento_total'),
            ])
            ->groupBy('rentals.commercial_user_id', 'users.name')
            ->orderByDesc('faturamento_total')
            ->get();

        return $rows->map(function ($row) {
            $row->grupo_id = $row->grupo_id !== null ? (int) $row->grupo_id : null;
            $row->faturamento_total = (float) $row->faturamento_total;
            $row->total_locacoes = (int) $row->total_locacoes;
            $row->ticket_medio = $row->total_locacoes > 0
                ? round($row->faturamento_total / $row->total_locacoes, 2)
                : 0.0;

            return $row;
        });
    }
}
