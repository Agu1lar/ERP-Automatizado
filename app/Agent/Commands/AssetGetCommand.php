<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class AssetGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'asset.get';
    }

    public static function description(): string
    {
        return 'Situação completa de um patrimônio: status, locação ativa, OS aberta e localização.';
    }

    public function permission(): string
    {
        return 'fleet.assets.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['asset_id', 'asset_codigo'],
            ],
            'properties' => [
                'asset_id' => ['type' => 'integer'],
                'asset_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $asset = $this->resolveAsset($input);
        $context = $this->contextBuilder->asset($asset);

        return $this->success(
            $this->formatMessage($context),
            $context,
            $this->buildNextSteps($context),
        );
    }

    /** @param  array<string, mixed>  $context */
    private function formatMessage(array $context): string
    {
        $asset = $context['asset'] ?? [];
        $rental = $context['active_rental'] ?? null;
        $order = $context['active_maintenance_order'] ?? null;

        $lines = [
            "**Patrimônio {$asset['codigo_patrimonio']}**",
            "• Equipamento: **{$asset['equipamento']}** ({$asset['categoria']})",
            "• Status: **{$asset['status_label']}**",
            "• Localização: {$asset['localizacao']}",
        ];

        if ($rental) {
            $lines[] = "• Locação ativa: **{$rental['codigo']}** — {$rental['status_label']} — {$rental['customer_nome']}";
        } else {
            $lines[] = '• Locação ativa: **nenhuma**';
        }

        if ($order) {
            $lines[] = "• OS aberta: **{$order['codigo']}** — {$order['status_label']}";
            if (! empty($order['descricao_problema'])) {
                $lines[] = '  Problema: '.$order['descricao_problema'];
            }
        } else {
            $lines[] = '• OS aberta: **nenhuma**';
        }

        $lines[] = "\nUse os atalhos para abrir a ficha ou avançar no fluxo.";

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $context @return list<array<string, mixed>> */
    private function buildNextSteps(array $context): array
    {
        $steps = [
            [
                'label' => 'Abrir ficha do patrimônio',
                'url' => $context['urls']['ficha'] ?? CopilotNavigationLinks::assets(),
                'primary' => true,
            ],
        ];

        if (! empty($context['active_rental']['url'])) {
            $steps[] = [
                'label' => 'Ver locação '.$context['active_rental']['codigo'],
                'url' => $context['active_rental']['url'],
            ];
        }

        if (! empty($context['active_maintenance_order']['url'])) {
            $steps[] = [
                'label' => 'Ver OS '.$context['active_maintenance_order']['codigo'],
                'url' => $context['active_maintenance_order']['url'],
            ];
        }

        return $steps;
    }
}
