<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;

class RentalGetCommand extends AbstractReadAgentCommand
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
    $context = $this->contextBuilder->rental($rental);
    $data = $context['rental'] ?? [];

    $message = "**Locação {$data['codigo']}** — {$data['status_label']}\n"
      .'• Cliente: **'.($data['customer']['nome'] ?? '—')."**\n"
      .'• Equipamento: **'.($data['asset']['descricao'] ?? '—')."**\n"
      .'• Retorno previsto: '.($data['expected_return_at'] ?? '—')."\n\n"
      .'Diga **saída**, **retorno**, **faturar** ou use os atalhos.';

    $nextSteps = [
      ['label' => 'Abrir ficha', 'url' => $context['urls']['ficha'] ?? route('rentals.show', $rental), 'primary' => true],
    ];

    return $this->success($message, $context, $nextSteps);
  }
}
