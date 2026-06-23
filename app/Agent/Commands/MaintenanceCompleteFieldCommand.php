<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Support\WorkflowNextStep;
use InvalidArgumentException;

class MaintenanceCompleteFieldCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly MaintenanceOrderService $maintenanceOrderService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'maintenance.complete_field';
  }

  public static function description(): string
  {
    return 'Conclui uma OS de manutenção em campo com checklist obrigatório (locação permanece ativa).';
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
    $checklistProperties = [];
    foreach (MaintenanceOrderService::CHECKLIST_CAMPO as $key => $label) {
      $checklistProperties[$key] = ['type' => 'boolean', 'description' => $label];
    }

    return [
      'type' => 'object',
      'oneOfRequired' => [
        ['order_id', 'order_codigo'],
      ],
      'oneOf' => [
        ['required' => ['checklist']],
        ['required' => ['confirm_checklist_all']],
      ],
      'properties' => [
        'order_id' => ['type' => 'integer'],
        'order_codigo' => ['type' => 'string'],
        'checklist' => [
          'type' => 'object',
          'properties' => $checklistProperties,
          'required' => array_keys(MaintenanceOrderService::CHECKLIST_CAMPO),
        ],
        'confirm_checklist_all' => [
          'type' => 'boolean',
          'description' => 'Atalho: marca todos os itens do checklist de campo como concluídos.',
        ],
        'solucao' => ['type' => 'string'],
        'horimetro' => ['type' => 'number'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $order = $this->resolveMaintenanceOrder($input);
    $checklist = $this->resolveChecklist($input);

    $order = $this->maintenanceOrderService->completeField(
      $order,
      $checklist,
      $input['solucao'] ?? null,
      isset($input['horimetro']) ? (float) $input['horimetro'] : null,
      $user,
    );

    return $this->success(
      "OS de campo {$order->codigo} concluída.",
      $this->contextBuilder->maintenanceOrder($order),
      WorkflowNextStep::maintenanceAfterComplete($order),
    );
  }

  /** @return array<string, bool> */
  private function resolveChecklist(array $input): array
  {
    if (! empty($input['confirm_checklist_all'])) {
      return array_fill_keys(array_keys(MaintenanceOrderService::CHECKLIST_CAMPO), true);
    }

    if (! empty($input['checklist']) && is_array($input['checklist'])) {
      return $input['checklist'];
    }

    throw new InvalidArgumentException('Informe checklist ou confirm_checklist_all=true.');
  }
}
