<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\PreventiveMaintenanceService;
use App\Support\CopilotNavigationLinks;

class PreventiveDueCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly PreventiveMaintenanceService $preventiveService,
    ) {}

    public static function name(): string
    {
        return 'preventive.due';
    }

    public static function description(): string
    {
        return 'Lista patrimônios com manutenção preventiva vencida por horímetro.';
    }

    public function permission(): string
    {
        return 'maintenance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $dueItems = $this->preventiveService->dueAssets();
        $slice = array_slice($dueItems, 0, $limit);
        $total = count($dueItems);

        $message = $total === 0
            ? 'Nenhum patrimônio com preventiva vencida no momento.'
            : "**Preventivas vencidas** — {$total} patrimônio(s)".($total > $limit ? " (mostrando {$limit})" : '').'.';

        return $this->success(
            $message,
            [
                'entity' => 'preventive_due',
                'count' => $total,
                'items' => collect($slice)->map(fn (array $item) => [
                    'asset_id' => $item['asset']->id,
                    'asset_codigo' => $item['asset']->codigo_patrimonio,
                    'equipamento' => $item['asset']->equipmentDisplayName(),
                    'horimetro' => $item['asset']->horimetro !== null ? (float) $item['asset']->horimetro : null,
                    'rule_id' => $item['rule']->id,
                    'rule_descricao' => $item['rule']->descricao,
                    'interval_horas' => (float) $item['rule']->interval_horas,
                    'horas_desde_ultima' => $item['horas_desde_ultima'],
                    'asset_url' => route('assets.show', $item['asset']),
                ])->all(),
            ],
            [
                ['label' => 'Abrir regras preventivas', 'url' => CopilotNavigationLinks::preventiveRules(), 'primary' => true],
            ],
        );
    }
}
