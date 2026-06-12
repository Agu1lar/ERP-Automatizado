<?php

namespace App\Agent;

use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalPricingPeriod;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\Domain\Rental\RentalQuote;
use App\Services\ReceivableTitleService;
use App\Support\CopilotNavigationLinks;
use App\Support\DelinquencyReportQuery;
use App\Support\FinanceDashboardQuery;
use App\Support\LogisticsDailyQuery;
use App\Support\RentalWorkflow;
use App\Support\WorkflowNextStep;
use Carbon\CarbonInterface;

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
      'document_exports' => [
        ['document_type' => 'rental_summary', 'rental_id' => $rental->id, 'label' => 'PDF resumo'],
        ['document_type' => 'rental_contract', 'rental_id' => $rental->id, 'label' => 'PDF contrato'],
      ],
      'urls' => [
        'ficha' => route('rentals.show', $rental),
        'pdf_resumo' => route('rentals.pdf', $rental),
        'pdf_contrato' => route('rentals.contract.pdf', $rental),
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
  public function asset(\App\Models\Domain\Fleet\Asset $asset): array
  {
    $asset->load([
      'equipmentModel.category',
      'yard',
    ]);

    $status = $asset->statusEnum();
    $activeRental = $asset->activeRental()?->load('customer');
    $activeOrder = $asset->activeMaintenanceOrder();
    $category = $asset->equipmentModel?->category;

    return [
      'entity' => 'asset',
      'asset' => [
        'id' => $asset->id,
        'codigo_patrimonio' => $asset->codigo_patrimonio,
        'equipamento' => $asset->equipmentDisplayName(),
        'categoria' => $category?->nome ?? '—',
        'status' => $status->value,
        'status_label' => $status->label(),
        'localizacao' => $asset->localizacao ?? '—',
        'yard' => $asset->yard?->nome,
        'horimetro' => $asset->horimetro,
      ],
      'active_rental' => $activeRental ? [
        'id' => $activeRental->id,
        'codigo' => $activeRental->codigo,
        'status' => $activeRental->status,
        'status_label' => $activeRental->statusEnum()->label(),
        'customer_nome' => $activeRental->customer?->nome,
        'expected_return_at' => $activeRental->expected_return_at?->toDateString(),
        'url' => route('rentals.show', $activeRental),
      ] : null,
      'active_maintenance_order' => $activeOrder ? [
        'id' => $activeOrder->id,
        'codigo' => $activeOrder->codigo,
        'status' => $activeOrder->status,
        'status_label' => $activeOrder->statusEnum()->label(),
        'descricao_problema' => $activeOrder->descricao_problema,
        'url' => route('maintenance.show', $activeOrder),
      ] : null,
      'suggested_commands' => array_values(array_filter([
        $activeRental && $activeRental->statusEnum()->value === 'reservado'
          ? ['command' => 'rental.checkout', 'params' => ['rental_id' => $activeRental->id]]
          : null,
        $activeRental && $activeRental->statusEnum()->value === 'locado'
          ? ['command' => 'rental.return', 'params' => ['rental_id' => $activeRental->id]]
          : null,
        ! $activeOrder && $status->value !== 'locado'
          ? ['command' => 'maintenance.open', 'params' => ['asset_id' => $asset->id, 'descricao' => 'Solicitação via copiloto']]
          : null,
      ])),
      'document_exports' => [
        ['document_type' => 'asset_sheet', 'asset_id' => $asset->id, 'label' => 'PDF ficha patrimônio'],
      ],
      'urls' => [
        'ficha' => route('assets.show', $asset),
        'pdf' => route('assets.pdf', $asset),
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
      'document_exports' => [
        ['document_type' => 'maintenance_order', 'order_id' => $order->id, 'label' => 'PDF OS'],
      ],
      'urls' => [
        'ficha' => route('maintenance.show', $order),
        'pdf' => route('maintenance.pdf', $order),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function quote(RentalQuote $quote): array
  {
    $quote->load(['asset.equipmentModel.category', 'customer', 'rental']);
    $status = $quote->statusEnum();

    return [
      'entity' => 'rental_quote',
      'quote' => [
        'id' => $quote->id,
        'codigo' => $quote->codigo,
        'status' => $status->value,
        'status_label' => $status->label(),
        'valid_until' => $quote->valid_until?->toDateString(),
        'expired' => $quote->isExpired(),
        'days_until_expiry' => $quote->daysUntilExpiry(),
        'valor_estimado' => $quote->valor_estimado !== null ? (float) $quote->valor_estimado : null,
        'expected_return_at' => $quote->expected_return_at?->toDateString(),
        'local_obra' => $quote->local_obra,
        'pricing_period' => $quote->pricing_period,
      ],
      'customer' => [
        'id' => $quote->customer_id,
        'nome' => $quote->customer?->nome,
      ],
      'asset' => [
        'id' => $quote->asset_id,
        'codigo_patrimonio' => $quote->asset?->codigo_patrimonio,
        'equipamento' => $quote->asset?->equipmentDisplayName(),
        'categoria' => $quote->asset?->equipmentModel?->category?->nome,
      ],
      'rental' => $quote->rental ? [
        'id' => $quote->rental->id,
        'codigo' => $quote->rental->codigo,
        'url' => route('rentals.show', $quote->rental),
      ] : null,
      'suggested_commands' => $status->canConvert()
        ? [['command' => 'quote.convert', 'params' => ['quote_id' => $quote->id]]]
        : [],
      'urls' => [
        'lista' => CopilotNavigationLinks::quotes(['q' => $quote->codigo]),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function receivableTitle(ReceivableTitle $title): array
  {
    $title->load(['customer', 'rental']);

    return [
      'entity' => 'receivable_title',
      'title' => [
        'id' => $title->id,
        'codigo' => $title->codigo,
        'status' => $title->status,
        'status_label' => $title->statusEnum()->label(),
        'valor' => (float) $title->valor,
        'vencimento' => $title->vencimento->toDateString(),
        'overdue' => $title->isOverdue(),
        'days_overdue' => $title->daysOverdue(),
        'parcel_label' => $title->parcelLabel(),
        'exported_to_erp' => $title->isExportedToErp(),
      ],
      'customer' => [
        'id' => $title->customer_id,
        'nome' => $title->customer?->nome,
      ],
      'rental' => $title->rental ? [
        'id' => $title->rental->id,
        'codigo' => $title->rental->codigo,
        'url' => route('rentals.show', $title->rental),
      ] : null,
      'suggested_commands' => $title->statusEnum()->value === 'aberto'
        ? [['command' => 'receivable.mark_paid', 'params' => ['title_id' => $title->id, 'payment_method' => 'pix']]]
        : [],
      'urls' => [
        'lista' => CopilotNavigationLinks::financeReceivables($title->codigo),
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

  /** @return array<string, mixed> */
  public function person(Person $person): array
  {
    $person->load(['company:id,nome,cnpj,tipo']);

    return [
      'entity' => 'person',
      'person' => [
        'id' => $person->id,
        'nome' => $person->nome,
        'cpf' => $person->cpf,
        'email' => $person->email,
        'telefone' => $person->telefone,
        'cargo' => $person->cargo,
        'ativo' => $person->ativo,
        'company' => $person->company ? [
          'id' => $person->company->id,
          'nome' => $person->company->nome,
          'cnpj' => $person->company->cnpj,
          'tipo' => $person->company->tipo,
        ] : null,
      ],
      'suggested_commands' => [
        ['command' => 'person.update', 'params' => ['person_id' => $person->id]],
      ],
      'urls' => [
        'ficha' => route('people.show', $person),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function company(Company $company): array
  {
    $company->loadCount(['people', 'contacts', 'emails']);

    return [
      'entity' => 'company',
      'company' => [
        'id' => $company->id,
        'nome' => $company->nome,
        'cnpj' => $company->cnpj,
        'tipo' => $company->tipo,
        'tipo_label' => $company->typeEnum()->label(),
        'endereco' => $company->endereco,
        'ativo' => $company->ativo,
        'people_count' => $company->people_count,
        'contacts_count' => $company->contacts_count,
        'emails_count' => $company->emails_count,
      ],
      'suggested_commands' => [
        ['command' => 'company.update', 'params' => ['company_id' => $company->id]],
        ['command' => 'person.search', 'params' => ['q' => $company->nome]],
      ],
      'urls' => [
        'lista' => route('companies.index'),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function billingEntry(RentalBillingQueueEntry $entry): array
  {
    $entry->load(['customer', 'rental.asset', 'receivableTitle']);
    $status = RentalBillingQueueStatus::tryFrom($entry->status);

    $suggestedCommands = match ($entry->statusEnum()) {
      RentalBillingQueueStatus::Pendente => [
        ['command' => 'billing.authorize_entry', 'params' => ['entry_id' => $entry->id]],
      ],
      RentalBillingQueueStatus::Autorizado => [
        ['command' => 'billing.invoice_entry', 'params' => ['entry_id' => $entry->id]],
      ],
      default => [],
    };

    return [
      'entity' => 'billing_entry',
      'entry' => [
        'id' => $entry->id,
        'codigo' => $entry->codigo,
        'tipo' => $entry->tipo,
        'status' => $entry->status,
        'status_label' => $status?->label(),
        'valor_car' => (float) $entry->valor_car,
        'valor_nf' => (float) $entry->valor_nf,
        'periodo_inicio' => $entry->periodo_inicio?->toDateString(),
        'periodo_fim' => $entry->periodo_fim?->toDateString(),
        'gerado_em' => $entry->gerado_em?->toIso8601String(),
        'customer' => [
          'id' => $entry->customer_id,
          'nome' => $entry->customer?->nome,
        ],
        'rental' => $entry->rental ? [
          'id' => $entry->rental->id,
          'codigo' => $entry->rental->codigo,
        ] : null,
        'receivable_title' => $entry->receivableTitle ? [
          'id' => $entry->receivableTitle->id,
          'codigo' => $entry->receivableTitle->codigo,
        ] : null,
      ],
      'suggested_commands' => $suggestedCommands,
      'document_exports' => [
        ['document_type' => 'billing_invoice', 'entry_id' => $entry->id, 'label' => 'PDF fatura'],
      ],
      'urls' => [
        'fila' => CopilotNavigationLinks::billingQueue(),
        'pdf' => route('finance.billing.pdf', $entry),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function yard(Yard $yard): array
  {
    $yard->loadCount('assets');

    return [
      'entity' => 'yard',
      'yard' => [
        'id' => $yard->id,
        'nome' => $yard->nome,
        'cidade' => $yard->cidade,
        'endereco' => $yard->endereco,
        'telefone' => $yard->telefone,
        'ativo' => $yard->ativo,
        'principal' => $yard->principal,
        'assets_count' => $yard->assets_count,
        'display_label' => $yard->displayLabel(),
      ],
      'suggested_commands' => [
        ['command' => 'yard.list', 'params' => ['q' => $yard->nome]],
      ],
      'urls' => [
        'lista' => CopilotNavigationLinks::yards($yard->nome),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function logisticsDaily(?CarbonInterface $date = null): array
  {
    $date = $date?->copy()->startOfDay() ?? now()->startOfDay();
    $query = app(LogisticsDailyQuery::class);
    $counts = $query->countsForDate($date);

    return [
      'entity' => 'logistics_daily',
      'date' => $date->toDateString(),
      'counts' => $counts,
      'suggested_commands' => [
        ['command' => 'logistics.daily', 'params' => ['date' => $date->toDateString()]],
      ],
      'urls' => [
        'lista' => CopilotNavigationLinks::logisticsDaily($date->toDateString()),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function pricingCategory(EquipmentCategory $category): array
  {
    $prices = EquipmentPricing::query()
      ->where('equipment_category_id', $category->id)
      ->whereNull('equipment_model_id')
      ->where('ativo', true)
      ->get()
      ->keyBy('periodo');

    $byPeriod = [];

    foreach (RentalPricingPeriod::cases() as $period) {
      $row = $prices->get($period->value);
      $byPeriod[$period->value] = $row ? (float) $row->valor : null;
    }

    $overrideCount = EquipmentPricing::query()
      ->whereNotNull('equipment_model_id')
      ->whereHas('equipmentModel', fn ($q) => $q->where('equipment_category_id', $category->id))
      ->where('ativo', true)
      ->count();

    return [
      'entity' => 'pricing_category',
      'category' => [
        'id' => $category->id,
        'nome' => $category->nome,
        'tipo_linha' => $category->tipo_linha,
        'ativo' => $category->ativo,
      ],
      'prices' => $byPeriod,
      'model_override_count' => $overrideCount,
      'urls' => [
        'tabela' => CopilotNavigationLinks::pricing(),
      ],
    ];
  }

  /** @return array<string, mixed> */
  public function partCatalogItem(PartCatalogItem $part): array
  {
    return [
      'entity' => 'part_catalog_item',
      'part' => [
        'id' => $part->id,
        'codigo_peca' => $part->codigo_peca,
        'codigo_alternativo' => $part->codigo_alternativo,
        'descricao' => $part->descricao,
        'valor_unitario_padrao' => (float) $part->valor_unitario_padrao,
        'estoque_atual' => (float) $part->estoque_atual,
        'estoque_minimo' => $part->estoque_minimo !== null ? (float) $part->estoque_minimo : null,
        'below_minimum' => $part->isBelowMinimum(),
        'ativo' => $part->ativo,
      ],
      'suggested_commands' => $part->isBelowMinimum()
        ? [['command' => 'part.list', 'params' => ['below_minimum_only' => true]]]
        : [],
      'urls' => [
        'catalogo' => CopilotNavigationLinks::partsCatalog($part->codigo_peca),
      ],
    ];
  }
}
