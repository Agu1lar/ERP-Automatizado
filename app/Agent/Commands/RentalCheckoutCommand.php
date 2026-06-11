<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Agent\AgentContextBuilder;
use App\Agent\Concerns\ResolvesAgentEntities;
use App\Models\User;
use App\Services\ReceivableTitleService;
use App\Services\RentalBillingService;
use App\Services\RentalService;
use App\Support\WorkflowNextStep;
use Carbon\Carbon;

class RentalCheckoutCommand extends AbstractAgentCommand
{
  use ResolvesAgentEntities;

  public function __construct(
    private readonly RentalService $rentalService,
    private readonly RentalBillingService $billingService,
    private readonly ReceivableTitleService $receivableTitleService,
    private readonly AgentContextBuilder $contextBuilder,
  ) {}

  public static function name(): string
  {
    return 'rental.checkout';
  }

  public static function description(): string
  {
    return 'Registra a saída (checklist) de uma locação reservada e inicializa o faturamento.';
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
        'title_due_date' => ['type' => 'string', 'format' => 'date'],
        'invoice_immediately' => ['type' => 'boolean'],
      ],
    ];
  }

  public function execute(array $input, User $user): AgentCommandResult
  {
    $rental = $this->resolveRental($input);

    $checklist = $input['checklist'] ?? array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);

    $rental = $this->rentalService->checkout(
      $rental,
      $checklist,
      $input['observacoes'] ?? null,
      $user,
    );

    if (! empty($input['title_due_date'])) {
      $entry = $rental->pendingBillingEntries()->with('receivableTitle')->first();

      if ($entry?->receivableTitle) {
        $this->receivableTitleService->updateOpenDueDate(
          $entry->receivableTitle,
          Carbon::parse($input['title_due_date']),
          $user,
        );
      }
    }

    if (! empty($input['invoice_immediately']) && $user->can('finance.manage')) {
      $entry = $rental->pendingBillingEntries()->first();

      if ($entry) {
        $this->billingService->authorizeAndInvoice($entry, $user);
      }
    }

    $rental = $rental->fresh();
    $nextSteps = array_map(fn (array $action) => [
      'label' => $action['label'],
      'url' => $action['url'] ?? null,
      'primary' => $action['primary'] ?? false,
    ], WorkflowNextStep::rentalAfterCheckout($rental));

    $pending = $rental->pendingBillingEntries()->first();
    if ($pending) {
      $nextSteps[] = [
        'label' => 'Autorizar fatura',
        'command' => 'billing.authorize_entry',
        'params' => ['entry_id' => $pending->id],
        'primary' => false,
      ];
    }

    return $this->success(
      "Saída registrada para {$rental->codigo}.",
      $this->contextBuilder->rental($rental),
      $nextSteps,
    );
  }
}
