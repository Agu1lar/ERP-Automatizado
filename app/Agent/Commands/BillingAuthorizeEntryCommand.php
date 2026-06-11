<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalBillingQueueStatus;
use App\Models\User;
use App\Services\RentalBillingService;

class BillingAuthorizeEntryCommand extends AbstractAgentCommand implements SupportsDryRun
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalBillingService $billingService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'billing.authorize_entry';
  }

  public static function description(): string
  {
    return 'Autoriza uma pendência na fila a faturar.';
  }

  public function permission(): string
  {
    return 'finance.manage';
  }

  public function inputSchema(): array
  {
    return [
      'type' => 'object',
      'oneOfRequired' => [
        ['entry_id', 'entry_codigo'],
      ],
      'properties' => [
        'entry_id' => ['type' => 'integer'],
        'entry_codigo' => ['type' => 'string'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $entry = $this->resolveBillingEntry($input);
    $entry = $this->billingService->authorizeEntry($entry, $user);

    return $this->success(
      "Pendência {$entry->codigo} autorizada.",
      [
        'entry' => [
          'id' => $entry->id,
          'codigo' => $entry->codigo,
          'status' => $entry->status,
          'rental_id' => $entry->rental_id,
        ],
        'rental' => $this->contextBuilder->rental($entry->rental),
      ],
      [
        [
          'label' => 'Gerar fatura',
          'command' => 'billing.invoice_entry',
          'params' => ['entry_id' => $entry->id],
          'primary' => true,
        ],
      ],
    );
  }

  public function dryRun(array $input, User $user): AgentCommandResult
  {
    $entry = $this->resolveBillingEntry($input);
    $entry->load(['customer', 'rental']);

    if ($entry->statusEnum() !== RentalBillingQueueStatus::Pendente) {
      return $this->failure(
        'Somente pendências em status pendente podem ser autorizadas.',
        'business_rule',
      );
    }

    return AgentCommandResult::preview(
      "Simulação: pendência {$entry->codigo} seria autorizada.",
      [
        'entry' => [
          'id' => $entry->id,
          'codigo' => $entry->codigo,
          'status_atual' => $entry->status,
          'status_novo' => RentalBillingQueueStatus::Autorizado->value,
          'valor_car' => (float) $entry->valor_car,
          'rental_codigo' => $entry->rental?->codigo,
          'customer_nome' => $entry->customer?->nome,
        ],
      ],
    );
  }
}
