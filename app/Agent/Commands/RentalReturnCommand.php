<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\RentalService;
use App\Support\WorkflowNextStep;

class RentalReturnCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalService $rentalService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'rental.return';
  }

  public static function description(): string
  {
    return 'Registra o retorno do equipamento (checklist) e coloca a locação em inspeção.';
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
      'properties' => [
        'rental_id' => ['type' => 'integer'],
        'rental_codigo' => ['type' => 'string'],
        'checklist' => ['type' => 'object'],
        'observacoes' => ['type' => 'string'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $rental = $this->resolveRental($input);
    $checklist = $input['checklist'] ?? array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);

    $rental = $this->rentalService->registerReturn(
      $rental,
      $checklist,
      $input['observacoes'] ?? null,
      $user,
    );

    return $this->success(
      "Retorno registrado para {$rental->codigo}.",
      $this->contextBuilder->rental($rental),
      array_merge(
        [
          [
            'label' => 'Concluir inspeção',
            'command' => 'rental.complete_inspection',
            'params' => ['rental_id' => $rental->id, 'outcome' => 'ok'],
            'primary' => true,
          ],
        ],
        WorkflowNextStep::rentalAfterReturn($rental),
      ),
    );
  }
}
