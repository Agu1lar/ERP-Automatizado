<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Enums\RentalPricingPeriod;
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use App\Support\CopilotPageContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsAgentApiFixtures;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('agent')]
class AgentApiTest extends TestCase
{
  use BuildsAgentApiFixtures;
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RolePermissionSeeder::class);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    gc_collect_cycles();
  }

  public function test_manifest_requires_agent_api_permission(): void
  {
    $user = $this->user(UserRole::Comercial);
    Sanctum::actingAs($user);

    $this->getJson('/api/agent/manifest')->assertForbidden();
  }

    public function test_manifest_lists_registered_commands(): void
    {
        $user = $this->agentUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/agent/manifest')
            ->assertOk()
            ->assertJsonPath('version', '1.5')
            ->assertJsonFragment(['name' => 'rental.get', 'surface' => 'visualization'])
            ->assertJsonFragment(['name' => 'rental.return', 'surface' => 'execution'])
            ->assertJsonFragment(['name' => 'maintenance.open', 'surface' => 'execution'])
            ->assertJsonFragment(['name' => 'maintenance.complete_field', 'surface' => 'execution'])
            ->assertJsonFragment(['name' => 'knowledge.get', 'surface' => 'visualization'])
            ->assertJsonFragment(['name' => 'receivable.mark_paid', 'surface' => 'execution'])
            ->assertJsonFragment(['name' => 'customer.search', 'surface' => 'visualization'])
            ->assertJsonFragment(['name' => 'billing.list_pending', 'surface' => 'visualization']);
    }

  public function test_agent_manifest_artisan_command_outputs_json(): void
  {
    $path = storage_path('app/agent-manifest-test.json');

    $this->artisan('agent:manifest', ['--output' => $path])->assertSuccessful();

    $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    @unlink($path);

    $this->assertSame('1.5', $payload['version']);
    $this->assertArrayHasKey('knowledge', $payload);
    $this->assertArrayHasKey('context_endpoints', $payload);
    $this->assertArrayHasKey('maintenance', $payload['context_endpoints']);
    $this->assertArrayHasKey('knowledge', $payload['context_endpoints']);
    $this->assertArrayHasKey('auth', $payload);
    $this->assertCount(15, array_filter(
      $payload['context_endpoints'],
      fn (string $key): bool => $key !== 'description',
      ARRAY_FILTER_USE_KEY,
    ));
  }

  public function test_maintenance_complete_field_command_via_api(): void
  {
    $user = $this->agentUser();
    Sanctum::actingAs($user);

    $asset = $this->asset('PAT-AGENT-CAMPO', AssetStatus::Disponivel);
    $customer = $this->customer();

    $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
    $rental = app(RentalService::class)->checkout(
      $rental,
      array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
    );

    $order = app(MaintenanceOrderService::class)->openField(
      $asset->fresh(),
      'Ajuste em obra',
      $rental,
      $user,
    );

    $this->postJson('/api/agent/commands/maintenance.complete_field', [
      'input' => [
        'order_id' => $order->id,
        'confirm_checklist_all' => true,
        'solucao' => 'Serviço concluído via agente',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.order.status', MaintenanceOrderStatus::Concluida->value)
      ->assertJsonPath('data.order.is_field', true);

    $asset->refresh();
    $rental->refresh();

    $this->assertSame(AssetStatus::Locado->value, $asset->status);
    $this->assertSame(RentalStatus::Locado->value, $rental->status);
    $this->assertSame(MaintenanceOrderType::Campo->value, $order->fresh()->tipo);
  }

  public function test_rental_get_context_by_codigo(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    Sanctum::actingAs($user);

    $this->getJson("/api/agent/context/rental/{$rental->codigo}")
      ->assertOk()
      ->assertJsonPath('entity', 'rental')
      ->assertJsonPath('rental.codigo', $rental->codigo);
  }

  public function test_rental_reserve_command_via_api(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    $asset = $this->asset('PAT-AGENT-1', AssetStatus::Disponivel);

    EquipmentPricing::create([
      'equipment_model_id' => $asset->equipment_model_id,
      'periodo' => RentalPricingPeriod::Diaria->value,
      'valor' => 100,
      'ativo' => true,
    ]);

    Sanctum::actingAs($user);

    $companyId = OperatingCompany::query()->value('id');

    $response = $this->postJson('/api/agent/commands/rental.reserve', [
      'input' => [
        'asset_id' => $asset->id,
        'customer_id' => $customer->id,
        'expected_return_at' => now()->addDays(5)->toDateString(),
      ],
    ], [
      'X-Operating-Company-Id' => (string) $companyId,
    ]);

    $response
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonStructure(['next_steps', 'data' => ['rental' => ['codigo']]]);

    $this->assertSame(1, Rental::query()->where('asset_id', $asset->id)->count());
  }

  public function test_finance_summary_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/finance.summary', ['input' => []])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'system');
  }

  public function test_blocked_customer_returns_business_error(): void
  {
    $user = $this->agentUser();
    $customer = Customer::create([
      'nome' => 'Bloqueado API',
      'cpf_cnpj' => '52998224725',
      'ativo' => true,
      'bloqueado' => true,
      'motivo_bloqueio' => 'Teste agente',
      'bloqueado_at' => now(),
      'bloqueado_by' => $user->id,
    ]);
    $asset = $this->asset('PAT-AGENT-BLK', AssetStatus::Disponivel);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/rental.reserve', [
      'input' => [
        'asset_id' => $asset->id,
        'customer_id' => $customer->id,
      ],
    ])
      ->assertStatus(422)
      ->assertJsonPath('ok', false);
  }

  public function test_quote_list_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/quote.list', ['input' => ['limit' => 5]])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'quote_list');
  }

  public function test_yard_list_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/yard.list', ['input' => []])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'yard_list');
  }

  public function test_receivable_list_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/receivable.list', ['input' => ['overdue_only' => true]])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'receivable_list');
  }

  public function test_manifest_includes_new_read_commands(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'quote.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'yard.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'receivable.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'receivable.get', 'surface' => 'visualization']);
  }

  public function test_checkout_declares_affected_resources(): void
  {
    $registry = app(\App\Agent\AgentCommandRegistry::class);
    $rental = $this->reservedRental();

    $resources = $registry->get('rental.checkout')->affectedResources([
      'rental_id' => $rental->id,
    ]);

    $this->assertNotEmpty($resources);
    $this->assertContains(['type' => 'rental', 'id' => $rental->id], $resources);
  }

  public function test_asset_list_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/asset.list', ['input' => ['limit' => 5]])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'asset_list');
  }

  public function test_maintenance_list_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/maintenance.list', ['input' => ['open_only' => true]])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'maintenance_list');
  }

  public function test_manifest_includes_asset_and_maintenance_reads(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'asset.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'maintenance.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'maintenance.get', 'surface' => 'visualization']);
  }

  public function test_quote_convert_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    $asset = $this->asset('PAT-QCONV', AssetStatus::Disponivel);

    EquipmentPricing::create([
      'equipment_model_id' => $asset->equipment_model_id,
      'periodo' => RentalPricingPeriod::Diaria->value,
      'valor' => 100,
      'ativo' => true,
    ]);

    $this->actingAs($user);
    $quote = app(\App\Services\RentalQuoteService::class)->create($asset, $customer, now()->addDays(5));
    $quote = app(\App\Services\RentalQuoteService::class)->send($quote, 7);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/quote.convert', [
      'input' => ['quote_codigo' => $quote->codigo],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'rental');
  }

  public function test_rental_cancel_command(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/rental.cancel', [
      'input' => [
        'rental_codigo' => $rental->codigo,
        'reason' => 'Cliente desistiu via copiloto',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true);

    $this->assertSame('cancelado', $rental->fresh()->status);
  }

  public function test_customer_update_block_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/customer.update', [
      'input' => [
        'customer_id' => $customer->id,
        'bloqueado' => true,
        'motivo_bloqueio' => 'Inadimplência acordada com gestor',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true);

    $customer->refresh();
    $this->assertTrue($customer->bloqueado);
    $this->assertSame('Inadimplência acordada com gestor', $customer->motivo_bloqueio);
  }

  public function test_manifest_includes_priority_execution_commands(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'quote.convert', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'quote.create', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'rental.update', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'billing.create_renewal', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'asset.move_location', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'person.search', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'company.search', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'rental.cancel', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'rental.extend', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'rental.substitute', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'customer.update', 'surface' => 'execution']);
  }

  public function test_quote_create_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    $asset = $this->asset('PAT-QCREATE', AssetStatus::Disponivel);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/quote.create', [
      'input' => [
        'asset_codigo' => $asset->codigo_patrimonio,
        'customer_id' => $customer->id,
        'local_obra' => 'Obra Copiloto',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'rental_quote');
  }

  public function test_rental_update_command(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/rental.update', [
      'input' => [
        'rental_codigo' => $rental->codigo,
        'local_obra' => 'Endereço atualizado via agente',
        'observacoes' => 'Nota do copiloto',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'rental');

    $rental->refresh();
    $this->assertSame('Endereço atualizado via agente', $rental->local_obra);
    $this->assertSame('Nota do copiloto', $rental->observacoes);
  }

  public function test_person_search_command(): void
  {
    $user = $this->agentUser();
    \App\Models\Domain\Person\Person::create([
      'nome' => 'Contato CRM Agente',
      'cpf' => '52998224725',
      'email' => 'crm@example.com',
      'ativo' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/person.search', [
      'input' => ['q' => 'Contato CRM'],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'person_search')
      ->assertJsonPath('data.count', 1);
  }

  public function test_asset_move_location_command(): void
  {
    $user = $this->agentUser();
    $asset = $this->asset('PAT-MOVE', AssetStatus::Disponivel);
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/asset.move_location', [
      'input' => [
        'asset_codigo' => $asset->codigo_patrimonio,
        'destino' => 'Pátio B',
        'motivo' => 'Reorganização',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'asset');

    $this->assertSame('Pátio B', $asset->fresh()->localizacao);
  }

  public function test_screen_context_includes_rental_json_on_show_page(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    $this->actingAs($user);

    $url = route('rentals.show', $rental);
    $page = CopilotPageContext::fromUrl($url);

    $structured = app(\App\Support\CopilotScreenContextResolver::class)->resolve(
      $user,
      $page['route'],
      $page['parameters'],
    );

    $this->assertNotNull($structured);
    $this->assertSame('rental', $structured['entity']);
    $this->assertSame($rental->codigo, $structured['rental']['codigo']);
  }

  public function test_screen_context_format_includes_json_block(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    $this->actingAs($user);

    $url = route('rentals.show', $rental);
    $page = CopilotPageContext::fromUrl($url);

    $formatted = app(\App\Support\CopilotScreenContextResolver::class)->formatForAgent(
      $user,
      $page['summary'],
      $page['route'],
      $page['url'],
      $page['parameters'],
    );

    $this->assertStringContainsString('Contexto estruturado da ficha atual', $formatted);
    $this->assertStringContainsString($rental->codigo, $formatted);
    $this->assertStringContainsString('```json', $formatted);
  }

  public function test_logistics_daily_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    $asset = $this->asset('PAT-LOG-DAY', AssetStatus::Disponivel);
    $today = now()->toDateString();

    \App\Models\Domain\Rental\Rental::create([
      'codigo' => 'LOC-LOG-DAY',
      'asset_id' => $asset->id,
      'customer_id' => $customer->id,
      'status' => \App\Enums\RentalStatus::Reservado->value,
      'local_obra' => 'Obra lista do dia',
      'reserved_at' => now(),
      'entrega_agendada_em' => $today,
      'entrega_turno' => 'manha',
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/logistics.daily', [
      'input' => ['date' => $today, 'section' => 'entregas'],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'logistics_daily')
      ->assertJsonPath('data.counts.entregas', 1)
      ->assertJsonFragment(['rental_codigo' => 'LOC-LOG-DAY']);
  }

  public function test_manifest_includes_logistics_daily_read(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'logistics.daily', 'surface' => 'visualization']);
  }

  public function test_finance_accounting_export_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();

    \App\Models\Domain\Finance\ReceivableTitle::create([
      'codigo' => 'TIT-AGENT-EXP',
      'customer_id' => $customer->id,
      'parcela' => 1,
      'total_parcelas' => 1,
      'valor' => 150.00,
      'vencimento' => now()->addDays(5),
      'status' => 'aberto',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/agent/commands/finance.accounting_export', [
      'input' => ['format' => 'omie', 'status' => 'aberto'],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'accounting_export');

    $url = $response->json('data.formats.0.url');
    $this->assertStringContainsString('format=omie', $url);
    $this->assertGreaterThanOrEqual(1, $response->json('data.count'));
  }

  public function test_manifest_includes_accounting_export_read(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'finance.accounting_export', 'surface' => 'visualization']);
  }

  public function test_rental_update_logistics_fields(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/rental.update', [
      'input' => [
        'rental_codigo' => $rental->codigo,
        'entrega_modalidade' => 'empresa_entrega',
        'entrega_agendada_em' => now()->addDay()->toDateString(),
        'entrega_turno' => 'manha',
        'horimetro_saida' => 1200,
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true);

    $rental->refresh();
    $this->assertSame('empresa_entrega', $rental->entrega_modalidade);
    $this->assertSame('manha', $rental->entrega_turno);
    $this->assertEquals(1200, (float) $rental->horimetro_saida);
  }

  public function test_search_global_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/search.global', [
      'input' => ['q' => 'Cliente Agente'],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'global_search')
      ->assertJsonFragment(['nome' => 'Cliente Agente']);
  }

  public function test_finance_delinquency_command(): void
  {
    $user = $this->agentUser();
    $customer = $this->customer();

    \App\Models\Domain\Finance\ReceivableTitle::create([
      'codigo' => 'TIT-OVERDUE',
      'customer_id' => $customer->id,
      'parcela' => 1,
      'total_parcelas' => 1,
      'valor' => 200.00,
      'vencimento' => now()->subDays(10),
      'status' => 'aberto',
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/finance.delinquency', [
      'input' => ['include_titles' => true, 'limit' => 5],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'finance_delinquency')
      ->assertJsonFragment(['codigo' => 'TIT-OVERDUE']);
  }

  public function test_billing_get_command(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();

    $entry = \App\Models\Domain\Rental\RentalBillingQueueEntry::create([
      'codigo' => 'FAT-AGENT-1',
      'rental_id' => $rental->id,
      'customer_id' => $rental->customer_id,
      'tipo' => 'ciclo',
      'status' => 'pendente',
      'valor_nf' => 500,
      'valor_car' => 500,
      'gerado_em' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/billing.get', [
      'input' => ['entry_codigo' => $entry->codigo],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'billing_entry')
      ->assertJsonPath('data.entry.codigo', 'FAT-AGENT-1');
  }

  public function test_person_create_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/person.create', [
      'input' => [
        'nome' => 'Contato Via Agente',
        'cpf' => '529.982.247-25',
        'email' => 'agente@example.com',
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'person');
  }

  public function test_manifest_includes_recommended_batch_commands(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->getJson('/api/agent/manifest')
      ->assertOk()
      ->assertJsonFragment(['name' => 'billing.get', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'finance.delinquency', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'search.global', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'person.create', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'company.update', 'surface' => 'execution'])
      ->assertJsonFragment(['name' => 'pricing.list', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'document.export', 'surface' => 'visualization'])
      ->assertJsonFragment(['name' => 'report.commercial', 'surface' => 'visualization'])
      ->assertJsonStructure(['document_exports' => ['types']]);
  }

  public function test_pricing_list_command(): void
  {
    $user = $this->agentUser();
    $asset = $this->asset('PAT-PRICING', AssetStatus::Disponivel);

    EquipmentPricing::create([
      'equipment_category_id' => $asset->equipmentModel->equipment_category_id,
      'periodo' => RentalPricingPeriod::Diaria->value,
      'valor' => 150,
      'ativo' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/pricing.list', ['input' => []])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'pricing_list');
  }

  public function test_report_commercial_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/commands/report.commercial', [
      'input' => [
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->toDateString(),
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.entity', 'report_commercial');
  }

  public function test_document_export_rental_pdf(): void
  {
    $user = $this->agentUser();
    $rental = $this->reservedRental();
    Sanctum::actingAs($user);

    $this->postJson('/api/agent/commands/document.export', [
      'input' => [
        'document_type' => 'rental_summary',
        'rental_codigo' => $rental->codigo,
      ],
    ])
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.document_type', 'rental_summary')
      ->assertJsonStructure(['data' => ['pdf_url']]);
  }
}
