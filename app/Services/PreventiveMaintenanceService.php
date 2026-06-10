<?php

namespace App\Services;

use App\Enums\MaintenanceOrderType;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PreventiveMaintenanceService
{
    public function createRule(
        int $equipmentModelId,
        float $intervalHoras,
        string $descricao,
        ?User $user = null,
    ): PreventiveMaintenanceRule {
        if ($intervalHoras <= 0) {
            throw new InvalidArgumentException('Intervalo de horas deve ser maior que zero.');
        }

        return PreventiveMaintenanceRule::create([
            'equipment_model_id' => $equipmentModelId,
            'interval_horas' => $intervalHoras,
            'descricao' => $descricao,
            'ativo' => true,
            'created_by' => $user?->id ?? auth()->id(),
        ]);
    }

    /** @return Collection<int, PreventiveMaintenanceRule> */
    public function rulesForModel(int $equipmentModelId): Collection
    {
        return PreventiveMaintenanceRule::query()
            ->with('equipmentModel.category')
            ->where('equipment_model_id', $equipmentModelId)
            ->active()
            ->orderBy('interval_horas')
            ->get();
    }

    /** @return Collection<int, MaintenanceOrder> */
    public function historyForAsset(Asset $asset): Collection
    {
        return MaintenanceOrder::query()
            ->with(['openedByUser', 'assignedToUser', 'preventiveRule'])
            ->where('asset_id', $asset->id)
            ->orderByDesc('opened_at')
            ->get();
    }

  /**
     * @return array{rule: PreventiveMaintenanceRule, horas_desde_ultima: float|null, proxima_em: float|null, vencida: bool, ultima_os: MaintenanceOrder|null}
     */
    public function statusForAssetRule(Asset $asset, PreventiveMaintenanceRule $rule): array
    {
        $ultimaPreventiva = MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->where('preventive_rule_id', $rule->id)
            ->where('tipo', MaintenanceOrderType::Preventiva->value)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->first();

        $horimetroAtual = $asset->horimetro !== null ? (float) $asset->horimetro : null;
        $horimetroBase = $ultimaPreventiva?->horimetro_servico !== null
            ? (float) $ultimaPreventiva->horimetro_servico
            : ($ultimaPreventiva ? null : 0.0);

        $horasDesde = ($horimetroAtual !== null && $horimetroBase !== null)
            ? max(0, $horimetroAtual - $horimetroBase)
            : null;

        $interval = (float) $rule->interval_horas;
        $proximaEm = $horasDesde !== null ? max(0, $interval - $horasDesde) : null;
        $vencida = $horasDesde !== null && $horasDesde >= $interval;

        return [
            'rule' => $rule,
            'horas_desde_ultima' => $horasDesde,
            'proxima_em' => $proximaEm,
            'vencida' => $vencida,
            'ultima_os' => $ultimaPreventiva,
        ];
    }

    /** @return list<array{asset: Asset, rule: PreventiveMaintenanceRule, horas_desde_ultima: float|null, vencida: bool}> */
    public function dueAssets(): array
    {
        $due = [];

        $rules = PreventiveMaintenanceRule::query()->active()->with('equipmentModel')->get();

        foreach ($rules as $rule) {
            $assets = Asset::query()
                ->where('equipment_model_id', $rule->equipment_model_id)
                ->get();

            foreach ($assets as $asset) {
                $status = $this->statusForAssetRule($asset, $rule);
                if ($status['vencida']) {
                    $due[] = [
                        'asset' => $asset,
                        'rule' => $rule,
                        'horas_desde_ultima' => $status['horas_desde_ultima'],
                        'vencida' => true,
                    ];
                }
            }
        }

        return $due;
    }

    public function countDueAssets(): int
    {
        return count($this->dueAssets());
    }
}
