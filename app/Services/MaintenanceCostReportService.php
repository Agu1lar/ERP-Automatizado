<?php

namespace App\Services;

use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MaintenanceCostReportService
{
    public function __construct(
        private readonly ProfitabilityReportService $profitabilityReport,
    ) {}

    /**
     * @return array{
     *     faturamento: float,
     *     custo_os: float,
     *     custo_pecas: float,
     *     custo_mao_obra: float,
     *     custo_externo: float,
     *     resultado: float,
     *     margem_percent: float|null,
     *     os_concluidas: int,
     *     locacoes: int,
     *     ratio_custo_faturamento_percent: float|null
     * }
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?string $region = null): array
    {
        $profit = $this->profitabilityReport->summary($from, $to, $region);
        $external = $this->totalExternalServiceCost($from, $to);
        $custoOs = round($profit['custo_manutencao'] + $external, 2);
        $faturamento = $profit['faturamento'];
        $resultado = round($faturamento - $custoOs, 2);

        return [
            'faturamento' => $faturamento,
            'custo_os' => $custoOs,
            'custo_pecas' => $profit['custo_pecas'],
            'custo_mao_obra' => $profit['custo_mao_obra'],
            'custo_externo' => $external,
            'resultado' => $resultado,
            'margem_percent' => $faturamento > 0
                ? round(($resultado / $faturamento) * 100, 1)
                : null,
            'os_concluidas' => $this->completedOrders($from, $to)->count(),
            'locacoes' => $profit['locacoes'],
            'ratio_custo_faturamento_percent' => $faturamento > 0
                ? round(($custoOs / $faturamento) * 100, 1)
                : null,
        ];
    }

    /** @return Collection<int, object> */
    public function byAsset(CarbonInterface $from, CarbonInterface $to, int $limit = 100, ?string $region = null): Collection
    {
        $rows = $this->profitabilityReport->byAsset($from, $to, $limit, $region);
        $externalByAsset = $this->externalCostGroupedByAsset($from, $to);

        return $rows->map(function (object $row) use ($externalByAsset) {
            $externo = (float) ($externalByAsset[$row->grupo_id]['custo'] ?? 0);
            $custoOs = round((float) $row->custo_manutencao + $externo, 2);
            $faturamento = (float) $row->faturamento;
            $resultado = round($faturamento - $custoOs, 2);

            return (object) [
                'grupo_id' => $row->grupo_id,
                'grupo_nome' => $row->grupo_nome,
                'faturamento' => $faturamento,
                'custo_pecas' => (float) $row->custo_pecas,
                'custo_mao_obra' => (float) $row->custo_mao_obra,
                'custo_externo' => $externo,
                'custo_os' => $custoOs,
                'resultado' => $resultado,
                'ratio_percent' => $faturamento > 0
                    ? round(($custoOs / $faturamento) * 100, 1)
                    : null,
                'margem_percent' => $faturamento > 0
                    ? round(($resultado / $faturamento) * 100, 1)
                    : null,
                'locacoes' => (int) $row->locacoes,
                'os_concluidas' => (int) $row->os_concluidas,
            ];
        })->sortByDesc('custo_os')->values();
    }

    /** @return Collection<int, object> */
    public function byCategory(CarbonInterface $from, CarbonInterface $to, ?string $region = null): Collection
    {
        $rows = $this->profitabilityReport->byCategory($from, $to, $region);
        $externalByCategory = $this->externalCostGroupedByCategory($from, $to);

        return $rows->map(function (object $row) use ($externalByCategory) {
            $externo = (float) ($externalByCategory[$row->grupo_id]['custo'] ?? 0);
            $custoOs = round((float) $row->custo_manutencao + $externo, 2);
            $faturamento = (float) $row->faturamento;
            $resultado = round($faturamento - $custoOs, 2);
            $osCount = max(
                (int) $row->os_concluidas,
                (int) ($externalByCategory[$row->grupo_id]['os'] ?? 0),
            );

            return (object) [
                'grupo_id' => $row->grupo_id,
                'grupo_nome' => $row->grupo_nome,
                'faturamento' => $faturamento,
                'custo_pecas' => (float) $row->custo_pecas,
                'custo_mao_obra' => (float) $row->custo_mao_obra,
                'custo_externo' => $externo,
                'custo_os' => $custoOs,
                'resultado' => $resultado,
                'ratio_percent' => $faturamento > 0
                    ? round(($custoOs / $faturamento) * 100, 1)
                    : null,
                'margem_percent' => $faturamento > 0
                    ? round(($resultado / $faturamento) * 100, 1)
                    : null,
                'locacoes' => (int) $row->locacoes,
                'os_concluidas' => $osCount,
            ];
        })->sortByDesc('custo_os')->values();
    }

    /** @return Collection<int, MaintenanceOrder> */
    public function completedOrders(CarbonInterface $from, CarbonInterface $to, ?int $assetId = null): Collection
    {
        return MaintenanceOrder::query()
            ->with(['asset.equipmentModel', 'parts', 'laborHours', 'rental.customer'])
            ->where('status', MaintenanceOrderStatus::Concluida->value)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $from->toDateString())
            ->whereDate('completed_at', '<=', $to->toDateString())
            ->when($assetId, fn ($query) => $query->where('asset_id', $assetId))
            ->orderByDesc('completed_at')
            ->get();
    }

    /** @return Collection<int, object> */
    public function orderRows(CarbonInterface $from, CarbonInterface $to, ?int $assetId = null): Collection
    {
        $revenueByAsset = $this->profitabilityReport
            ->byAsset($from, $to, 500)
            ->keyBy('grupo_id');

        return $this->completedOrders($from, $to, $assetId)->map(function (MaintenanceOrder $order) use ($revenueByAsset) {
            $assetRevenue = (float) ($revenueByAsset[$order->asset_id]->faturamento ?? 0);

            return (object) [
                'order' => $order,
                'codigo' => $order->codigo,
                'tipo' => $order->tipoEnum()->label(),
                'asset_label' => $order->asset?->codigo_patrimonio ?? '—',
                'asset_id' => $order->asset_id,
                'completed_at' => $order->completed_at,
                'custo_total' => $order->totalCost(),
                'custo_pecas' => $order->totalPartsCost(),
                'custo_mao_obra' => $order->totalLaborCost(),
                'custo_externo' => (float) ($order->valor_servico_externo ?? 0),
                'faturamento_patrimonio_periodo' => $assetRevenue,
                'ratio_percent' => $assetRevenue > 0
                    ? round(($order->totalCost() / $assetRevenue) * 100, 1)
                    : null,
            ];
        });
    }

    public function totalExternalServiceCost(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) MaintenanceOrder::query()
            ->where('status', MaintenanceOrderStatus::Concluida->value)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $from->toDateString())
            ->whereDate('completed_at', '<=', $to->toDateString())
            ->sum('valor_servico_externo');
    }

    /** @return array<int, array{custo: float, os: int}> */
    private function externalCostGroupedByAsset(CarbonInterface $from, CarbonInterface $to): array
    {
        return MaintenanceOrder::query()
            ->where('status', MaintenanceOrderStatus::Concluida->value)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $from->toDateString())
            ->whereDate('completed_at', '<=', $to->toDateString())
            ->selectRaw('asset_id, COALESCE(SUM(valor_servico_externo), 0) as custo, COUNT(*) as os')
            ->groupBy('asset_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->asset_id => [
                    'custo' => (float) $row->custo,
                    'os' => (int) $row->os,
                ],
            ])
            ->all();
    }

    /** @return array<int, array{custo: float, os: int}> */
    private function externalCostGroupedByCategory(CarbonInterface $from, CarbonInterface $to): array
    {
        return MaintenanceOrder::query()
            ->join('assets', 'maintenance_orders.asset_id', '=', 'assets.id')
            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')
            ->where('maintenance_orders.status', MaintenanceOrderStatus::Concluida->value)
            ->whereNotNull('maintenance_orders.completed_at')
            ->whereDate('maintenance_orders.completed_at', '>=', $from->toDateString())
            ->whereDate('maintenance_orders.completed_at', '<=', $to->toDateString())
            ->selectRaw('equipment_models.equipment_category_id as category_id, COALESCE(SUM(maintenance_orders.valor_servico_externo), 0) as custo, COUNT(*) as os')
            ->groupBy('equipment_models.equipment_category_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->category_id => [
                    'custo' => (float) $row->custo,
                    'os' => (int) $row->os,
                ],
            ])
            ->all();
    }
}
