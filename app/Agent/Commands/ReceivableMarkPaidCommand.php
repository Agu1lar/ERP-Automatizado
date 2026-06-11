<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Agent\Contracts\SupportsDryRun;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Models\User;
use App\Services\ReceivableTitleService;

class ReceivableMarkPaidCommand extends AbstractAgentCommand implements SupportsDryRun
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly ReceivableTitleService $receivableTitleService,
  ) {}

  public static function name(): string
  {
    return 'receivable.mark_paid';
  }

  public static function description(): string
  {
    return 'Registra baixa manual de um título a receber.';
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
        ['title_id', 'title_codigo'],
      ],
      'required' => ['payment_method'],
      'properties' => [
        'title_id' => ['type' => 'integer'],
        'title_codigo' => ['type' => 'string'],
        'payment_method' => ['type' => 'string', 'enum' => ['dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'outro']],
        'observacoes' => ['type' => 'string'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $title = $this->resolveReceivableTitle($input);
    $method = PaymentMethod::from($input['payment_method']);

    $title = $this->receivableTitleService->markAsPaid(
      $title,
      $method,
      $input['observacoes'] ?? null,
      null,
      $user,
    );

    return $this->success(
      "Título {$title->codigo} baixado com sucesso.",
      [
        'title' => [
          'id' => $title->id,
          'codigo' => $title->codigo,
          'status' => $title->status,
          'valor' => (float) $title->valor,
          'pago_em' => $title->pago_em?->toIso8601String(),
        ],
      ],
      [
        [
          'label' => 'Ver títulos',
          'url' => route('finance.receivables', ['q' => $title->codigo]),
          'primary' => true,
        ],
      ],
    );
  }

  public function dryRun(array $input, User $user): AgentCommandResult
  {
    $title = $this->resolveReceivableTitle($input);
    $title->load('customer');
    $method = PaymentMethod::from($input['payment_method']);

    if ($title->statusEnum() !== ReceivableTitleStatus::Aberto) {
      return $this->failure('Somente títulos abertos podem receber baixa.', 'business_rule');
    }

    return AgentCommandResult::preview(
      "Simulação: título {$title->codigo} seria baixado via {$method->label()}.",
      [
        'title' => [
          'id' => $title->id,
          'codigo' => $title->codigo,
          'status_atual' => $title->status,
          'status_novo' => ReceivableTitleStatus::Pago->value,
          'valor' => (float) $title->valor,
          'vencimento' => $title->vencimento->toDateString(),
          'customer_nome' => $title->customer?->nome,
          'payment_method' => $method->value,
        ],
      ],
    );
  }
}
