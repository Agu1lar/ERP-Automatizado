<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\RentalBillingQueueStatus;
use App\Models\User;
use App\Services\RentalBillingService;

class BillingInvoiceEntryCommand extends AbstractAgentCommand implements SupportsDryRun
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalBillingService $billingService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'billing.invoice_entry';
  }

  public static function description(): string
  {
    return 'Gera a fatura de uma pendência autorizada (ou autoriza e fatura se ainda pendente).';
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
    $entry = $this->billingService->authorizeAndInvoice($entry, $user);
    $entry->load(['receivableTitle', 'rental']);

    return $this->success(
      "Fatura {$entry->codigo} gerada.",
      [
        'entry' => [
          'id' => $entry->id,
          'codigo' => $entry->codigo,
          'status' => $entry->status,
          'receivable_title_codigo' => $entry->receivableTitle?->codigo,
        ],
        'rental' => $this->contextBuilder->rental($entry->rental),
      ],
      [
        [
          'label' => 'Ver título',
          'url' => route('finance.receivables', ['q' => $entry->receivableTitle?->codigo ?? '']),
          'primary' => true,
        ],
      ],
    );
  }

  public function dryRun(array $input, User $user): AgentCommandResult
  {
    $entry = $this->resolveBillingEntry($input);
    $entry->load(['customer', 'rental', 'receivableTitle']);

    if (! in_array($entry->statusEnum(), [RentalBillingQueueStatus::Pendente, RentalBillingQueueStatus::Autorizado], true)) {
      return $this->failure('Esta pendência não pode ser faturada.', 'business_rule');
    }

    if ((float) $entry->valor_car <= 0) {
      return $this->failure('Valor a receber deve ser maior que zero.', 'business_rule');
    }

    $willAuthorize = $entry->statusEnum() === RentalBillingQueueStatus::Pendente;

    return AgentCommandResult::preview(
      "Simulação: fatura {$entry->codigo} — R$ ".number_format((float) $entry->valor_car, 2, ',', '.').
      ($willAuthorize ? ' (autorizará e faturará)' : ''),
      [
        'entry' => [
          'id' => $entry->id,
          'codigo' => $entry->codigo,
          'status_atual' => $entry->status,
          'status_novo' => RentalBillingQueueStatus::Faturado->value,
          'valor_car' => (float) $entry->valor_car,
          'autorizar_antes' => $willAuthorize,
          'rental_codigo' => $entry->rental?->codigo,
          'customer_nome' => $entry->customer?->nome,
          'title_existente' => $entry->receivableTitle?->codigo,
        ],
      ],
    );
  }
}
