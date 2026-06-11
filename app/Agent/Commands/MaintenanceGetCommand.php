<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Support\CopilotNavigationLinks;

class MaintenanceGetCommand extends AbstractReadAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'maintenance.get';
    }

    public static function description(): string
    {
        return 'Retorna o contexto completo de uma ordem de serviço (status, patrimônio, próximos passos).';
    }

    public function permission(): string
    {
        return 'maintenance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'oneOfRequired' => [
                ['order_id', 'order_codigo'],
            ],
            'properties' => [
                'order_id' => ['type' => 'integer'],
                'order_codigo' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $order = $this->resolveMaintenanceOrder($input);
        $context = $this->contextBuilder->maintenanceOrder($order);

        return $this->success(
            "OS **{$order->codigo}** — {$order->statusEnum()->label()}.",
            $context,
            [
                [
                    'label' => 'Abrir ficha da OS',
                    'url' => $context['urls']['ficha'] ?? CopilotNavigationLinks::maintenance(),
                    'primary' => true,
                ],
            ],
        );
    }
}
