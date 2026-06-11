<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Support\WorkflowNextStep;

class MaintenanceStartCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly MaintenanceOrderService $maintenanceOrderService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'maintenance.start';
  }

  public static function description(): string
  {
    return 'Inicia a execução de uma OS aberta.';
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
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $order = $this->resolveMaintenanceOrder($input);
    $order = $this->maintenanceOrderService->start($order, $user);

    return $this->success(
      "OS {$order->codigo} em execução.",
      $this->contextBuilder->maintenanceOrder($order),
      WorkflowNextStep::maintenanceAfterStart($order),
    );
  }
}
