<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FleetAnalyticsService
{
    public function __construct(
        private readonly ProfitabilityReportService $profitabilityReport,
        private readonly AssetDepreciationService $depreciationService,
    ) {}

    public function periodDays(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * @return Collection<int, object{
     *     grupo_id: int|string,
     *     grupo_nome: string,
     *     patrimonios: int,
     *     dias_periodo: int,
     *     dias_comprometidos: int,
     *     taxa_ocupacao: float,
     *     locacoes: int
     * }>
     */
    public function occupancy(CarbonInterface $from, CarbonInterface $to, string $groupBy = 'asset', ?string $region = null): Collection
    {
        $daysInPeriod = $this->periodDays($from, $to);
        $assets = $this->rentableAssets();
        $rentals = $this->rentalsOverlapping($from, $to, $assets->pluck('id'), $region);

        $perAsset = $assets->map(function (Asset $asset) use ($from, $to, $daysInPeriod, $rentals) {
            $assetRentals = $rentals->where('asset_id', $asset->id);
            $committed = $this->committedDaysForAsset($assetRentals, $from, $to);

            return (object) [
                'asset' => $asset,
                'dias_periodo' => $daysInPeriod,
                'dias_comprometidos' => $committed,
                'taxa_ocupacao' => $daysInPeriod > 0 ? round(($committed / $daysInPeriod) * 100, 1) : 0.0,
                'locacoes' => $assetRentals->count(),
            ];
        });

        return match ($groupBy) {
            'category' => $this->groupOccupancy($perAsset, 'category', $daysInPeriod),
            'model' => $this->groupOccupancy($perAsset, 'model', $daysInPeriod),
            default => $perAsset->map(fn (object $row) => (object) [
                'grupo_id' => $row->asset->id,
                'grupo_nome' => $row->asset->codigo_patrimonio.' — '.($row->asset->equipmentModel?->displayName() ?? ''),
                'patrimonios' => 1,
                'dias_periodo' => $row->dias_periodo,
                'dias_comprometidos' => $row->dias_comprometidos,
                'taxa_ocupacao' => $row->taxa_ocupacao,
                'locacoes' => $row->locacoes,
            ])->sortByDesc('taxa_ocupacao')->values(),
        };
    }

    /**
     * @return array{
     *     dias_periodo: int,
     *     dias_comprometidos: int,
     *     taxa_ocupacao: float,
     *     patrimonios: int,
     *     locacoes: int
     * }
     */
    public function occupancySummary(CarbonInterface $from, CarbonInterface $to, ?string $region = null): array
    {
        $rows = $this->occupancy($from, $to, 'asset', $region);
        $daysInPeriod = $this->periodDays($from, $to);
        $patrimonios = $rows->count();
        $totalCommitted = (int) $rows->sum('dias_comprometidos');
        $denominator = max(1, $daysInPeriod * max(1, $patrimonios));

        return [
            'dias_periodo' => $daysInPeriod,
            'dias_comprometidos' => $totalCommitted,
            'taxa_ocupacao' => round(($totalCommitted / $denominator) * 100, 1),
            'patrimonios' => $patrimonios,
            'locacoes' => (int) $rows->sum('locacoes'),
        ];
    }

    /**
     * Rentabilidade por patrimônio incluindo valor de compra.
     *
     * @return Collection<int, object>
     */
    public function profitabilityByAsset(CarbonInterface $from, CarbonInterface $to, int $limit = 100, ?string $region = null): Collection
    {
        $rows = $this->profitabilityReport->byAsset($from, $to, $limit, $region);
        $assets = Asset::query()
            ->whereIn('id', $rows->pluck('grupo_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($assets) {
            $asset = $assets[$row->grupo_id] ?? null;
            $valorCompra = $asset?->valor_compra !== null ? (float) $asset->valor_compra : null;
            $resultadoOperacional = (float) $row->resultado;
            $retornoCompra = $valorCompra > 0
                ? round(((float) $row->faturamento / $valorCompra) * 100, 1)
                : null;

            return (object) [
                'grupo_id' => $row->grupo_id,
                'grupo_nome' => $row->grupo_nome,
                'faturamento' => (float) $row->faturamento,
                'custo_pecas' => (float) $row->custo_pecas,
                'custo_mao_obra' => (float) $row->custo_mao_obra,
                'custo_manutencao' => (float) $row->custo_manutencao,
                'resultado' => $resultadoOperacional,
                'locacoes' => (int) $row->locacoes,
                'os_concluidas' => (int) $row->os_concluidas,
                'valor_compra' => $valorCompra,
                'resultado_operacional' => $resultadoOperacional,
                'resultado_apos_compra' => $valorCompra !== null
                    ? round($resultadoOperacional - $valorCompra, 2)
                    : null,
                'retorno_sobre_compra_percent' => $retornoCompra,
            ];
        });
    }

    /**
     * ROI, payback, valor contábil e sugestão de desinvestimento por patrimônio.
     *
     * @return Collection<int, object>
     */
    public function investmentAnalysis(CarbonInterface $from, CarbonInterface $to, int $limit = 100, ?string $region = null): Collection
    {
        $periodRows = $this->profitabilityByAsset($from, $to, $limit, $region);
        $assets = Asset::query()
            ->with('equipmentModel.category')
            ->whereIn('id', $periodRows->pluck('grupo_id'))
            ->get()
            ->keyBy('id');

        $lifetimeRevenue = $this->lifetimeRevenueByAsset($periodRows->pluck('grupo_id'));
        $occupancyByAsset = $this->occupancy($from, $to, 'asset', $region)->keyBy('grupo_id');

        return $periodRows->map(function (object $row) use ($assets, $from, $to, $lifetimeRevenue, $occupancyByAsset) {
            $asset = $assets[$row->grupo_id] ?? null;
            $valorCompra = $asset?->valor_compra !== null ? (float) $asset->valor_compra : null;
            $bookValue = $asset ? $this->depreciationService->bookValue($asset, $to) : null;
            $lifetime = (float) ($lifetimeRevenue[$row->grupo_id] ?? 0);
            $monthsInPeriod = max(1, $this->periodDays($from, $to) / 30);
            $monthlyNet = ((float) $row->resultado_operacional) / $monthsInPeriod;

            $paybackMonths = null;
            if ($valorCompra > 0 && $monthlyNet > 0) {
                $paybackMonths = (int) ceil($valorCompra / $monthlyNet);
            }

            $roiLifetimePercent = $valorCompra > 0
                ? round((($lifetime - (float) $row->custo_manutencao - $valorCompra) / $valorCompra) * 100, 1)
                : null;

            $taxaOcupacao = (float) ($occupancyByAsset[$row->grupo_id]->taxa_ocupacao ?? 0.0);
            $divestment = $this->divestmentRecommendation(
                $row,
                $taxaOcupacao,
                $paybackMonths,
                $monthlyNet,
            );

            return (object) [
                'grupo_id' => $row->grupo_id,
                'grupo_nome' => $row->grupo_nome,
                'faturamento' => (float) $row->faturamento,
                'custo_manutencao' => (float) $row->custo_manutencao,
                'resultado_operacional' => (float) $row->resultado_operacional,
                'valor_compra' => $valorCompra,
                'valor_contabil' => $bookValue,
                'depreciacao_acumulada' => $asset ? $this->depreciationService->accumulatedDepreciation($asset, $to) : null,
                'faturamento_vida_util' => $lifetime,
                'roi_vida_util_percent' => $roiLifetimePercent,
                'payback_meses' => $paybackMonths,
                'taxa_ocupacao' => $taxaOcupacao,
                'divestir' => $divestment['flag'],
                'divestir_motivo' => $divestment['reason'],
            ];
        });
    }

    /** @return Collection<int, object> */
    public function divestmentSuggestions(CarbonInterface $from, CarbonInterface $to, ?string $region = null): Collection
    {
        return $this->investmentAnalysis($from, $to, 200, $region)
            ->filter(fn (object $row) => $row->divestir)
            ->values();
    }

    /**
     * @return array{
     *     month: string,
     *     month_label: string,
     *     days: list<int>,
     *     assets: list<array{id: int, label: string, days: array<string, string>}>,
     *     legend: array<string, string>
     * }
     */
    public function availabilityCalendar(
        CarbonInterface $month,
        ?int $categoryId = null,
        ?int $modelId = null,
        int $assetLimit = 40,
    ): array {
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $days = range(1, (int) $end->day);

        $assets = Asset::query()
            ->with('equipmentModel.category')
            ->whereNotIn('status', [
                AssetStatus::Sucata->value,
                AssetStatus::Arquivado->value,
                AssetStatus::Cancelado->value,
            ])
            ->when($categoryId, fn ($q) => $q->whereHas(
                'equipmentModel',
                fn ($m) => $m->where('equipment_category_id', $categoryId),
            ))
            ->when($modelId, fn ($q) => $q->where('equipment_model_id', $modelId))
            ->orderBy('codigo_patrimonio')
            ->limit($assetLimit)
            ->get();

        $rentals = $this->rentalsOverlapping($start, $end, $assets->pluck('id'));
        $maintenance = $this->maintenanceOverlapping($start, $end, $assets->pluck('id'));

        $assetRows = $assets->map(function (Asset $asset) use ($start, $end, $days, $rentals, $maintenance) {
            $dayMap = [];
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                $day = (string) $cursor->day;
                $dayMap[$day] = $this->availabilityStateForDay(
                    $asset,
                    $cursor,
                    $rentals->where('asset_id', $asset->id),
                    $maintenance->where('asset_id', $asset->id),
                );
                $cursor->addDay();
            }

            return [
                'id' => $asset->id,
                'label' => $asset->codigo_patrimonio.' — '.($asset->equipmentModel?->displayName() ?? ''),
                'days' => $dayMap,
            ];
        })->values()->all();

        return [
            'month' => $start->format('Y-m'),
            'month_label' => $start->translatedFormat('F Y'),
            'days' => $days,
            'assets' => $assetRows,
            'legend' => [
                'livre' => 'Livre',
                'reservado' => 'Reservado',
                'locado' => 'Locado',
                'inspecao' => 'Em inspeção',
                'manutencao' => 'Manutenção',
                'indisponivel' => 'Indisponível',
            ],
        ];
    }

    /** @return Collection<int, EquipmentCategory> */
    public function categoryOptions(): Collection
    {
        return EquipmentCategory::query()->where('ativo', true)->orderBy('nome')->get();
    }

    /** @return Collection<int, EquipmentModel> */
    public function modelOptions(): Collection
    {
        return EquipmentModel::query()->where('ativo', true)->orderBy('marca')->orderBy('modelo')->get();
    }

    /** @param Collection<int, Rental> $rentals */
    private function committedDaysForAsset(Collection $rentals, CarbonInterface $from, CarbonInterface $to): int
    {
        $days = collect();

        foreach ($rentals as $rental) {
            foreach ($this->rentalCommittedDayKeys($rental, $from, $to) as $key) {
                $days->put($key, true);
            }
        }

        return $days->count();
    }

    /** @return list<string> */
    private function rentalCommittedDayKeys(Rental $rental, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($rental->statusEnum() === RentalStatus::Cancelado) {
            return [];
        }

        $start = $rental->scheduleStart();

        if ($start === null) {
            return [];
        }

        $end = match ($rental->statusEnum()) {
            RentalStatus::Concluido => $rental->completed_at?->copy()->startOfDay()
                ?? $rental->returned_at?->copy()->startOfDay(),
            default => $rental->returned_at?->copy()->startOfDay() ?? $to->copy()->startOfDay(),
        };

        if ($end === null || $end->lt($start)) {
            return [];
        }

        $overlapStart = $start->greaterThan($from) ? $start : $from->copy()->startOfDay();
        $overlapEnd = $end->lessThan($to) ? $end : $to->copy()->startOfDay();

        if ($overlapEnd->lt($overlapStart)) {
            return [];
        }

        $keys = [];
        $cursor = $overlapStart->copy();

        while ($cursor->lte($overlapEnd)) {
            $keys[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $keys;
    }

    /** @param Collection<int, object> $perAsset */
    private function groupOccupancy(Collection $perAsset, string $type, int $daysInPeriod): Collection
    {
        return $perAsset
            ->groupBy(function (object $row) use ($type) {
                if ($type === 'category') {
                    return $row->asset->equipmentModel?->equipment_category_id ?? 0;
                }

                return $row->asset->equipment_model_id ?? 0;
            })
            ->map(function (Collection $items, $groupId) use ($type, $daysInPeriod) {
                $first = $items->first();
                $name = match ($type) {
                    'category' => $first->asset->equipmentModel?->category?->nome ?? 'Sem categoria',
                    default => $first->asset->equipmentModel?->displayName() ?? 'Sem modelo',
                };
                $count = $items->count();
                $committed = (int) $items->sum('dias_comprometidos');
                $denominator = max(1, $daysInPeriod * $count);

                return (object) [
                    'grupo_id' => $groupId,
                    'grupo_nome' => $name,
                    'patrimonios' => $count,
                    'dias_periodo' => $daysInPeriod,
                    'dias_comprometidos' => $committed,
                    'taxa_ocupacao' => round(($committed / $denominator) * 100, 1),
                    'locacoes' => (int) $items->sum('locacoes'),
                ];
            })
            ->sortByDesc('taxa_ocupacao')
            ->values();
    }

    /** @param Collection<int, Rental> $rentals */
    /** @param Collection<int, MaintenanceOrder> $maintenance */
    private function availabilityStateForDay(
        Asset $asset,
        CarbonInterface $day,
        Collection $rentals,
        Collection $maintenance,
    ): string {
        foreach ($rentals as $rental) {
            if ($rental->statusEnum() === RentalStatus::Cancelado) {
                continue;
            }

            $scheduleStart = $rental->scheduleStart();
            $checkoutAt = $rental->checkout_at?->startOfDay();
            $returnedAt = $rental->returned_at?->startOfDay();
            $completedAt = $rental->completed_at?->startOfDay();

            if ($scheduleStart === null || $day->lt($scheduleStart)) {
                continue;
            }

            $rentalEnd = $rental->statusEnum() === RentalStatus::Concluido
                ? ($completedAt ?? $returnedAt)
                : null;

            if ($rentalEnd !== null && $day->gt($rentalEnd)) {
                continue;
            }

            if ($checkoutAt === null || $day->lt($checkoutAt)) {
                return 'reservado';
            }

            if ($returnedAt !== null && $day->gte($returnedAt)) {
                return 'inspecao';
            }

            return 'locado';
        }

        foreach ($maintenance as $order) {
            if ($order->statusEnum() === MaintenanceOrderStatus::Cancelada) {
                continue;
            }

            $opened = $order->opened_at?->startOfDay() ?? $order->created_at?->startOfDay();

            if ($opened === null || $day->lt($opened)) {
                continue;
            }

            $closed = $order->statusEnum() === MaintenanceOrderStatus::Concluida
                ? $order->completed_at?->startOfDay()
                : null;

            if ($closed === null || $day->lte($closed)) {
                return 'manutencao';
            }
        }

        $status = $asset->statusEnum();

        if (in_array($status, [AssetStatus::Bloqueado, AssetStatus::Sucata, AssetStatus::Arquivado, AssetStatus::Cancelado], true)) {
            return 'indisponivel';
        }

        if (in_array($status, [AssetStatus::EmManutencao, AssetStatus::AguardandoPeca], true) && $day->isToday()) {
            return 'manutencao';
        }

        return 'livre';
    }

    /** @return Collection<int, Asset> */
    private function rentableAssets(): Collection
    {
        return Asset::query()
            ->with('equipmentModel.category')
            ->whereNotIn('status', [
                AssetStatus::Sucata->value,
                AssetStatus::Arquivado->value,
                AssetStatus::Cancelado->value,
            ])
            ->orderBy('codigo_patrimonio')
            ->get();
    }

    /** @param Collection<int, int|string>|array<int, int|string> $assetIds */
    private function rentalsOverlapping(CarbonInterface $from, CarbonInterface $to, Collection|array $assetIds, ?string $region = null): Collection
    {
        $ids = collect($assetIds)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Rental::query()
            ->whereIn('asset_id', $ids)
            ->inGeographicRegion($region)
            ->where('status', '!=', RentalStatus::Cancelado->value)
            ->where(function ($query) use ($from, $to) {
                $query->where(function ($q) use ($from, $to) {
                    $q->whereNotNull('reserved_at')
                        ->whereDate('reserved_at', '<=', $to->toDateString())
                        ->where(function ($inner) use ($from) {
                            $inner->whereNull('completed_at')
                                ->orWhereDate('completed_at', '>=', $from->toDateString());
                        });
                });
            })
            ->get();
    }

    /** @param Collection<int, int|string>|array<int, int|string> $assetIds */
    private function maintenanceOverlapping(CarbonInterface $from, CarbonInterface $to, Collection|array $assetIds): Collection
    {
        $ids = collect($assetIds)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MaintenanceOrder::query()
            ->whereIn('asset_id', $ids)
            ->where('status', '!=', MaintenanceOrderStatus::Cancelada->value)
            ->whereDate('opened_at', '<=', $to->toDateString())
            ->where(function ($query) use ($from) {
                $query->whereNull('completed_at')
                    ->orWhereDate('completed_at', '>=', $from->toDateString());
            })
            ->get();
    }

    /** @param  Collection<int, int|string>  $assetIds */
    /** @return array<int, float> */
    private function lifetimeRevenueByAsset(Collection $assetIds): array
    {
        if ($assetIds->isEmpty()) {
            return [];
        }

        return Rental::query()
            ->whereIn('asset_id', $assetIds)
            ->where('status', RentalStatus::Concluido->value)
            ->selectRaw('asset_id, COALESCE(SUM(valor_faturamento), 0) as total')
            ->groupBy('asset_id')
            ->pluck('total', 'asset_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /** @return array{flag: bool, reason: string|null} */
    private function divestmentRecommendation(
        object $row,
        float $occupancyRate,
        ?int $paybackMonths,
        float $monthlyNet,
    ): array {
        $minOccupancy = (float) config('fleet.divestment.min_occupancy_percent', 15);
        $maxPayback = (int) config('fleet.divestment.max_payback_months', 60);

        if ((float) $row->resultado_operacional < 0 && (float) $row->custo_manutencao > (float) $row->faturamento) {
            return [
                'flag' => true,
                'reason' => 'Custo de manutenção supera o faturamento no período.',
            ];
        }

        if ($occupancyRate < $minOccupancy && (float) $row->faturamento <= 0) {
            return [
                'flag' => true,
                'reason' => "Ocupação abaixo de {$minOccupancy}% sem faturamento relevante.",
            ];
        }

        if ($paybackMonths !== null && $paybackMonths > $maxPayback && $monthlyNet > 0) {
            return [
                'flag' => true,
                'reason' => "Payback estimado acima de {$maxPayback} meses.",
            ];
        }

        if ((float) $row->resultado_operacional < 0 && $occupancyRate < $minOccupancy) {
            return [
                'flag' => true,
                'reason' => 'Resultado negativo com baixa ocupação.',
            ];
        }

        return ['flag' => false, 'reason' => null];
    }
}
