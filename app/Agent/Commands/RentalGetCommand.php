<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;

class RentalGetCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'rental.get';
  }

  public static function description(): string
  {
    return 'Retorna o contexto completo de uma locação (status, faturamento, títulos, fluxo).';
  }

  public function permission(): string
  {
    return 'rentals.view';
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
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $rental = $this->resolveRental($input);

    return $this->success(
      "Contexto da locação {$rental->codigo}.",
      $this->contextBuilder->rental($rental),
    );
  }
}
