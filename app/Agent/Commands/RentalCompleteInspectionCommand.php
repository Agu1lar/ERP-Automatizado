<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\RentalService;

class RentalCompleteInspectionCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalService $rentalService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'rental.complete_inspection';
  }

  public static function description(): string
  {
    return 'Conclui a inspeção pós-retorno (ok, manutenção ou indenização).';
  }

  public function permission(): string
  {
    return 'rentals.operate';
  }

  /** @return list<array{type: string, id: int}> */
  public function affectedResources(array $input): array
  {
    return $this->affectedResourcesForRental($input);
  }

  protected function declaredResourceTypes(): array
  {
    return ['rental', 'asset'];
  }

  public function inputSchema(): array
  {
    return [
      'type' => 'object',
      'oneOfRequired' => [
        ['rental_id', 'rental_codigo'],
      ],
      'required' => ['outcome'],
      'properties' => [
        'rental_id' => ['type' => 'integer'],
        'rental_codigo' => ['type' => 'string'],
        'outcome' => ['type' => 'string', 'enum' => ['ok', 'maintenance', 'indenizacao']],
        'motivo' => ['type' => 'string'],
        'valor_indenizacao' => ['type' => 'number'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $rental = $this->resolveRental($input);
    $outcome = $input['outcome'] ?? 'ok';

    if ($outcome === 'indenizacao') {
      $rental = $this->rentalService->completeInspectionWithIndemnity(
        $rental,
        $input['motivo'] ?? 'Indenização registrada via copiloto',
        (float) ($input['valor_indenizacao'] ?? 0),
        $user,
      );
    } elseif ($outcome === 'maintenance') {
      $rental = $this->rentalService->completeInspection(
        $rental,
        true,
        $input['motivo'] ?? 'Manutenção pós-retorno',
        $user,
      );
    } else {
      $rental = $this->rentalService->completeInspection($rental, false, null, $user);
    }

    $order = $rental->maintenanceOrders()->latest('id')->first();
    $nextSteps = [];

    if ($order && in_array($outcome, ['maintenance', 'indenizacao'], true)) {
      $nextSteps[] = [
        'label' => "Abrir OS {$order->codigo}",
        'url' => route('maintenance.show', $order),
        'primary' => true,
      ];
      $nextSteps[] = [
        'label' => 'Iniciar OS',
        'command' => 'maintenance.start',
        'params' => ['order_id' => $order->id],
      ];
    }

    return $this->success(
      "Inspeção concluída — locação {$rental->codigo} finalizada.",
      $this->contextBuilder->rental($rental->fresh()),
      $nextSteps,
    );
  }
}
