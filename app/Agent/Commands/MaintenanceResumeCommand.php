<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Support\WorkflowNextStep;

class MaintenanceResumeCommand extends AbstractAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly MaintenanceOrderService $maintenanceOrderService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'maintenance.resume';
    }

    public static function description(): string
    {
        return 'Retoma a execução de uma OS que aguardava peça.';
    }

    public function permission(): string
    {
        return 'maintenance.operate';
    }

    /** @return list<array{type: string, id: int}> */
    public function affectedResources(array $input): array
    {
        return $this->affectedResourcesForMaintenanceOrder($input);
    }

    protected function declaredResourceTypes(): array
    {
        return ['maintenance_order', 'asset', 'rental'];
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
        $order = $this->maintenanceOrderService->resume($order, $user);

        return $this->success(
            "OS {$order->codigo} retomada em execução.",
            $this->contextBuilder->maintenanceOrder($order),
            WorkflowNextStep::maintenanceAfterResume($order),
        );
    }
}
