<?php

namespace App\Agent\Commands;

use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Support\WorkflowNextStep;

class MaintenanceWaitPartCommand extends AbstractAgentCommand
{
    use ResolvesAgentEntities;

    public function __construct(
        private readonly MaintenanceOrderService $maintenanceOrderService,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public static function name(): string
    {
        return 'maintenance.wait_part';
    }

    public static function description(): string
    {
        return 'Marca uma OS em execução como aguardando peça.';
    }

    public function permission(): string
    {
        return 'maintenance.operate';
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
                'observacao' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $order = $this->resolveMaintenanceOrder($input);
        $order = $this->maintenanceOrderService->waitForPart(
            $order,
            $input['observacao'] ?? null,
            $user,
        );

        return $this->success(
            "OS {$order->codigo} aguardando peça.",
            $this->contextBuilder->maintenanceOrder($order),
            WorkflowNextStep::maintenanceAfterWait($order),
        );
    }
}
