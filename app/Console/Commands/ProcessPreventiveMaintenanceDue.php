<?php

namespace App\Console\Commands;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Services\MaintenanceOrderService;
use App\Services\PreventiveMaintenanceService;
use App\Support\ActiveOperatingCompany;
use Illuminate\Console\Command;

class ProcessPreventiveMaintenanceDue extends Command
{
    protected $signature = 'maintenance:process-preventive-due {--dry-run : Apenas listar, sem abrir OS}';

    protected $description = 'Processa patrimônios com manutenção preventiva vencida e abre OS quando configurado';

    public function handle(
        PreventiveMaintenanceService $preventiveService,
        MaintenanceOrderService $maintenanceOrderService,
    ): int {
        $dueItems = collect(ActiveOperatingCompany::forEach(
            fn () => $preventiveService->dueAssets()
        ))->flatten(1)->all();
        $opened = 0;
        $skipped = 0;

        foreach ($dueItems as $item) {
            $asset = $item['asset'];
            $rule = $item['rule'];

            if ($asset->statusEnum() !== AssetStatus::Disponivel) {
                $this->line("Ignorado {$asset->codigo_patrimonio}: status {$asset->statusEnum()->label()}");
                $skipped++;

                continue;
            }

            $hasOpenOrder = MaintenanceOrder::query()
                ->where('asset_id', $asset->id)
                ->where('preventive_rule_id', $rule->id)
                ->whereIn('status', [
                    MaintenanceOrderStatus::Aberta->value,
                    MaintenanceOrderStatus::EmExecucao->value,
                    MaintenanceOrderStatus::AguardandoPeca->value,
                ])
                ->exists();

            if ($hasOpenOrder) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("Vencida: {$asset->codigo_patrimonio} — {$rule->descricao}");
                $opened++;

                continue;
            }

            if (! config('maintenance.auto_open_preventive_orders', true)) {
                $skipped++;

                continue;
            }

            try {
                $order = $maintenanceOrderService->openPreventive($asset, $rule);
                $this->info("OS {$order->codigo} aberta para {$asset->codigo_patrimonio}");
                $opened++;
            } catch (\InvalidArgumentException $e) {
                $this->warn("Falha {$asset->codigo_patrimonio}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info("Concluído: {$opened} processado(s), {$skipped} ignorado(s), ".count($dueItems).' vencido(s) no total.');

        return self::SUCCESS;
    }
}
