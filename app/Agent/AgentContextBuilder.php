<?php

namespace App\Agent;

use App\Enums\RentalBillingQueueStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Services\ReceivableTitleService;
use App\Support\DelinquencyReportQuery;
use App\Support\FinanceDashboardQuery;
use App\Support\RentalWorkflow;
use App\Support\WorkflowNextStep;

class AgentContextBuilder
{
  /** @return array<string, mixed> */
  public function rental(Rental $rental): array
  {
    $rental->load([
      'customer',
      'asset.equipmentModel.category',
      'receivableTitles',
      'billingQueueEntries.receivableTitle',
      'items',
    ]);

    $status = $rental->statusEnum();
    $workflow = RentalWorkflow::steps($rental);

    $pendingBilling = $rental->billingQueueEntries
      ->filter(fn ($e) => in_array($e->statusEnum(), [
        RentalBillingQueueStatus::Pendente,
        RentalBillingQueueStatus::Autorizado,
      ], true))
      ->values();

    $suggestedCommands = match (true) {
      $status->value === 'reservado' => [
        ['command' => 'rental.checkout', 'params' => ['rental_id' => $rental->id]],
      ],
      $status->value === 'locado' => [
        ['command' => 'rental.return', 'params' => ['rental_id' => $rental->id]],
      ],
      $status->value === 'em_inspecao' => [
        ['command' => 'rental.complete_inspection', 'params' => ['rental_id' => $rental->id, 'outcome' => 'ok']],
      ],
      $pendingBilling->isNotEmpty() => [
        ['command' => 'billing.authorize_entry', 'params' => ['entry_id' => $pendingBilling->first()->id]],
      ],
      default => [],
    };

    return [
      'entity' => 'rental',
      'rental' => [
        'id' => $rental->id,
        'codigo' => $rental->codigo,
        'status' => $status->value,
        'status_label' => $status->label(),
        'customer' => [
          'id' => $rental->customer_id,
          'nome' => $rental->customer?->nome,
        ],
        'asset' => [
          'id' => $rental->asset_id,
          'codigo_patrimonio' => $rental->asset?->codigo_patrimonio,
          'descricao' => $rental->asset?->equipmentDisplayName(),
        ],
        'valor_faturamento' => $rental->valor_faturamento,
        'expected_return_at' => $rental->expected_return_at?->toDateString(),
        'checkout_at' => $rental->checkout_at?->toIso8601String(),
        'next_billing_at' => $rental->next_billing_at?->toDateString(),
      ],
      'workflow' => $workflow,
      'billing_queue' => $pendingBilling->map(fn ($e) => [
        'id' => $e->id,
        'codigo' => $e->codigo,
        'tipo' => $e->tipo,
        'status' => $e->status,
        'valor_car' => (float) $e->valor_car,
        'title_codigo' => $e->receivableTitle?->codigo,
        'title_vencimento' => $e->receivableTitle?->vencimento?->toDateString(),
      ])->all(),
      'receivable_titles' => $rental->receivableTitles->map(fn ($t) => [
        'id' => $t->id,
        'codigo' => $t->codigo,
        'valor' => (float) $t->valor,
        'vencimento' => $t->vencimento->toDateString(),
        'status' => $t->status,
        'overdue' => $t->isOverdue(),
      ])->all(),
      'suggested_commands' => $suggestedCommands,
      'urls' => [
        'ficha' => route('rentals.show', $rental),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function customer(Customer $customer): array
  {
    $finance = app(ReceivableTitleService::class);
    $delinquency = app(DelinquencyReportQuery::class);

    $openBalance = $finance->customerOpenBalance($customer);
    $overdueBalance = $finance->customerOverdueBalance($customer);
    $hasOverdue = $finance->customerHasOverdueTitles($customer);

    return [
      'entity' => 'customer',
      'customer' => [
        'id' => $customer->id,
        'nome' => $customer->nome,
        'cpf_cnpj' => $customer->formattedDocument(),
        'bloqueado' => $customer->isManuallyBlocked(),
        'motivo_bloqueio' => $customer->motivo_bloqueio,
        'bloqueio_inadimplencia' => (bool) $customer->bloqueio_inadimplencia,
        'limite_credito' => $customer->limite_credito,
      ],
      'finance' => [
        'saldo_aberto' => $openBalance,
        'saldo_atrasado' => $overdueBalance,
        'tem_atraso' => $hasOverdue,
        'aging_summary' => $delinquency->summary(),
      ],
      'urls' => [
        'ficha' => route('customers.show', $customer),
        'inadimplencia' => route('finance.delinquency'),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function maintenanceOrder(MaintenanceOrder $order): array
  {
    $order->load(['asset.equipmentModel', 'rental.customer', 'assignedToUser']);

    $status = $order->statusEnum();

    return [
      'entity' => 'maintenance_order',
      'order' => [
        'id' => $order->id,
        'codigo' => $order->codigo,
        'status' => $status->value,
        'status_label' => $status->label(),
        'tipo' => $order->tipo,
        'descricao_problema' => $order->descricao_problema,
        'asset_codigo' => $order->asset?->codigo_patrimonio,
        'rental_codigo' => $order->rental?->codigo,
      ],
      'next_step_hint' => WorkflowNextStep::maintenanceStatusHint($status),
      'suggested_commands' => match ($status->value) {
        'aberta' => [['command' => 'maintenance.start', 'params' => ['order_id' => $order->id]]],
        'em_execucao' => [
          ['command' => 'maintenance.wait_part', 'params' => ['order_id' => $order->id]],
          ['command' => 'maintenance.complete', 'params' => ['order_id' => $order->id]],
        ],
        'aguardando_peca' => [['command' => 'maintenance.resume', 'params' => ['order_id' => $order->id]]],
        default => [],
      },
      'urls' => [
        'ficha' => route('maintenance.show', $order),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function systemSnapshot(): array
  {
    $financeDashboard = app(FinanceDashboardQuery::class);
    $delinquency = app(DelinquencyReportQuery::class);

    return [
      'entity' => 'system',
      'finance' => [
        'receivable_this_week' => $financeDashboard->receivableThisWeekSummary(),
        'billing_cycle_due_count' => $financeDashboard->billingCycleDueCount(),
        'pending_renewal_queue_count' => $financeDashboard->pendingRenewalQueueCount(),
        'delinquency' => $delinquency->summary(),
      ],
    ];
  }
}
