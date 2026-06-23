<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Enums\MaintenanceOrderType;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Support\WorkflowNextStep;

class MaintenanceOpenCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly MaintenanceOrderService $maintenanceOrderService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'maintenance.open';
  }

  public static function description(): string
  {
    return 'Abre uma ordem de serviço para um patrimônio.';
  }

  public function permission(): string
  {
    return 'maintenance.manage';
  }

  /** @return list<array{type: string, id: int}> */
  public function affectedResources(array $input): array
  {
    return $this->affectedResourcesForAssetOpenMaintenance($input);
  }

  protected function declaredResourceTypes(): array
  {
    return ['asset', 'rental'];
  }

  public function inputSchema(): array
  {
    return [
      'type' => 'object',
      'oneOfRequired' => [
        ['asset_id', 'asset_codigo'],
      ],
      'required' => ['descricao'],
      'properties' => [
        'asset_id' => ['type' => 'integer'],
        'asset_codigo' => ['type' => 'string'],
        'rental_id' => ['type' => 'integer'],
        'rental_codigo' => ['type' => 'string'],
        'descricao' => ['type' => 'string'],
        'tipo' => ['type' => 'string', 'enum' => ['corretiva', 'preventiva', 'retorno_locacao', 'indenizacao', 'campo']],
        'impeditiva' => ['type' => 'boolean'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $asset = $this->resolveAsset($input);
    $rental = null;

    if (! empty($input['rental_id']) || ! empty($input['rental_codigo'])) {
      $rental = $this->resolveRental($input);
    }

    $tipo = MaintenanceOrderType::from($input['tipo'] ?? MaintenanceOrderType::Corretiva->value);

    if ($tipo === MaintenanceOrderType::Campo) {
      $rental ??= $asset->activeRental();
      $order = $this->maintenanceOrderService->openField(
        $asset,
        $input['descricao'],
        $rental,
        $user,
      );
    } else {
      $order = $this->maintenanceOrderService->open(
        $asset,
        $input['descricao'],
        $tipo,
        impeditiva: (bool) ($input['impeditiva'] ?? true),
        rental: $rental,
        user: $user,
      );
    }

    $nextSteps = $tipo === MaintenanceOrderType::Campo
      ? [
        [
          'label' => 'Abrir manutenção em campo',
          'url' => route('field.maintenance.scan', $asset->codigo_patrimonio),
          'primary' => true,
        ],
      ]
      : [
        [
          'label' => 'Iniciar execução',
          'command' => 'maintenance.start',
          'params' => ['order_id' => $order->id],
          'primary' => true,
        ],
        ...WorkflowNextStep::maintenanceAfterStart($order),
      ];

    return $this->success(
      "OS {$order->codigo} aberta.",
      $this->contextBuilder->maintenanceOrder($order),
      $nextSteps,
    );
  }
}
