<?php

namespace Tests\Feature;

use App\Agent\Chat\AgentHeuristicParser;
use App\Enums\AssetStatus;
use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Enums\UserRole;
use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\Domain\Agent\AgentMessage;
use App\Models\Domain\Agent\AgentSession;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use App\Livewire\Admin\AgentLogIndex;
use App\Livewire\Copilot\CopilotPanel;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('agent')]
class AgentCopilotTest extends TestCase
{
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

  public function test_heuristic_parser_detects_finance_summary(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Me mostra o resumo financeiro');

    $this->assertSame('finance.summary', $parsed['command'] ?? null);
  }

  public function test_heuristic_parser_detects_rental_return(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Registrar retorno da LOC-000042');

    $this->assertSame('rental.return', $parsed['command'] ?? null);
    $this->assertSame('LOC-000042', $parsed['input']['rental_codigo'] ?? null);
  }

  public function test_heuristic_parser_detects_quote_convert(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Converter orçamento ORC-000001 em reserva');

    $this->assertSame('quote.convert', $parsed['command'] ?? null);
    $this->assertSame('ORC-000001', $parsed['input']['quote_codigo'] ?? null);
  }

  public function test_heuristic_parser_detects_rental_cancel(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Cancelar reserva LOC-000010 motivo: cliente desistiu');

    $this->assertSame('rental.cancel', $parsed['command'] ?? null);
    $this->assertSame('LOC-000010', $parsed['input']['rental_codigo'] ?? null);
  }

  public function test_heuristic_parser_detects_customer_block(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Bloquear cliente Obra Central motivo: inadimplência');

    $this->assertSame('customer.update', $parsed['command'] ?? null);
    $this->assertTrue($parsed['input']['bloqueado'] ?? false);
  }

  public function test_heuristic_parser_detects_billing_list_pending(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Mostrar pendências a faturar');

    $this->assertSame('billing.list_pending', $parsed['command'] ?? null);
  }

  public function test_heuristic_parser_detects_logistics_daily(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Lista do dia logística — entregas de hoje');

    $this->assertSame('logistics.daily', $parsed['command'] ?? null);
    $this->assertSame('entregas', $parsed['input']['section'] ?? null);
  }

  public function test_heuristic_parser_detects_rental_filter_for_betoneiras(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse(
      'Quero que você filtre os contratos que tem betoneiras locadas',
    );

    $this->assertSame('rental.list', $parsed['command'] ?? null);
    $this->assertSame('locado', $parsed['input']['status'] ?? null);
  }

  public function test_rental_list_betoneiras_returns_panel_navigation_link(): void
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $category = EquipmentCategory::create([
      'nome' => 'Betoneira',
      'tipo_linha' => 'linha_leve',
      'ativo' => true,
    ]);

    $model = EquipmentModel::create([
      'equipment_category_id' => $category->id,
      'marca' => 'M',
      'modelo' => '400L',
      'ativo' => true,
    ]);

    $customer = Customer::create([
      'nome' => 'Obra Betoneira',
      'cpf_cnpj' => '39053344705',
      'ativo' => true,
    ]);

    $asset = app(AssetStatusService::class)->createWithInitialStatus(new Asset([
      'codigo_patrimonio' => 'PAT-BET-1',
      'equipment_model_id' => $model->id,
      'localizacao' => 'Pátio',
    ]), AssetStatus::Disponivel);

    $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
    app(RentalService::class)->checkout(
      $rental,
      array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
    );

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/agent/chat', [
      'message' => 'Filtrar contratos com betoneiras locadas',
    ])->assertOk();

    $response->assertJsonPath('executed', true);
    $response->assertJsonPath('command', 'rental.list');

    $actions = $response->json('actions') ?? [];
    $panelAction = collect($actions)->first(fn ($a) => str_contains($a['url'] ?? '', 'aba=painel'));

    $this->assertNotNull($panelAction);
    $this->assertStringContainsString('escopo=locado', $panelAction['url']);
    $this->assertStringContainsString('categoria='.$category->id, $panelAction['url']);
    $this->assertTrue($panelAction['primary'] ?? false);
  }

  public function test_rental_filter_without_category_does_not_return_unrelated_equipment(): void
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $escavadeira = EquipmentCategory::create([
      'nome' => 'Escavadeira',
      'tipo_linha' => 'pesada',
      'ativo' => true,
    ]);

    $model = EquipmentModel::create([
      'equipment_category_id' => $escavadeira->id,
      'marca' => 'CAT',
      'modelo' => '320D',
      'ativo' => true,
    ]);

    $customer = Customer::create([
      'nome' => 'Cliente Escav',
      'cpf_cnpj' => '52998224725',
      'ativo' => true,
    ]);

    $asset = app(AssetStatusService::class)->createWithInitialStatus(new Asset([
      'codigo_patrimonio' => 'PAT-ESC-1',
      'equipment_model_id' => $model->id,
      'localizacao' => 'Pátio',
    ]), AssetStatus::Disponivel);

    $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
    app(RentalService::class)->checkout(
      $rental,
      array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
    );

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/agent/chat', [
      'message' => 'Quero que você filtre os contratos que tem betoneiras locadas',
    ])->assertOk();

    $response->assertJsonPath('executed', true);
    $reply = (string) $response->json('reply');
    $this->assertStringContainsString('Betoneira', $reply);
    $this->assertStringNotContainsString('CAT 320D', $reply);
    $this->assertSame(0, $response->json('result.data.count'));
  }

  public function test_asset_get_returns_status_and_open_orders(): void
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $category = EquipmentCategory::create([
      'nome' => 'Gerador',
      'tipo_linha' => 'linha_leve',
      'ativo' => true,
    ]);

    $model = EquipmentModel::create([
      'equipment_category_id' => $category->id,
      'marca' => 'Yanmar',
      'modelo' => 'YDG',
      'ativo' => true,
    ]);

    app(AssetStatusService::class)->createWithInitialStatus(new Asset([
      'codigo_patrimonio' => 'AC-TEST-1',
      'equipment_model_id' => $model->id,
      'localizacao' => 'Pátio central',
    ]), AssetStatus::Disponivel);

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/chat', [
      'message' => 'Me fale a situação do patrimônio AC-TEST-1',
    ])
      ->assertOk()
      ->assertJsonPath('executed', true)
      ->assertJsonPath('command', 'asset.get')
      ->assertJsonPath('result.data.asset.codigo_patrimonio', 'AC-TEST-1');
  }

  public function test_rental_stats_by_category_in_period(): void
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $category = EquipmentCategory::create([
      'nome' => 'Martelete',
      'tipo_linha' => 'linha_leve',
      'ativo' => true,
    ]);

    Sanctum::actingAs($user);

    $parsed = app(AgentHeuristicParser::class)->parse('Quantos marteletes foram locados este mês?');
    $this->assertSame('rental.stats', $parsed['command'] ?? null);

    $this->postJson('/api/agent/chat', [
      'message' => 'Quantos marteletes foram locados este mês?',
    ])
      ->assertOk()
      ->assertJsonPath('command', 'rental.stats')
      ->assertJsonPath('executed', true);
  }

  public function test_ask_mode_blocks_mutating_command_execution(): void
  {
    Sanctum::actingAs($this->agentUser());

    $response = $this->postJson('/api/agent/chat', [
      'message' => 'Registrar retorno da LOC-000099',
      'mode' => 'ask',
    ])->assertOk();

    $this->assertStringContainsString('Agente', (string) $response->json('reply'));
    $this->assertFalse((bool) $response->json('executed'));
  }

  public function test_copilot_document_requires_llm_configuration(): void
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    Livewire::test(CopilotPanel::class)
      ->call('togglePanel')
      ->set('mode', 'ask')
      ->set('queuedAttachments', [[
        'path' => 'agent-intake/test/sample.txt',
        'mime' => 'text/plain',
        'original_name' => 'contrato.txt',
      ]])
      ->set('prompt', 'Extraia os dados do cliente')
      ->call('sendMessage')
      ->assertSet('isOpen', true)
      ->assertSee('não consigo ler documentos anexos');
  }

  public function test_heuristic_parser_detects_customer_billing_batch(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Copiloto, faturar ciclos pendentes da Construtora X');

    $this->assertSame('billing.process_customer_pending', $parsed['command'] ?? null);
    $this->assertSame('Construtora X', $parsed['input']['customer_name'] ?? null);
    $this->assertSame('authorize_and_invoice', $parsed['input']['action'] ?? null);
  }

  public function test_process_customer_pending_dry_run_via_chat(): void
  {
    $user = $this->agentUser();
    $rental = $this->createRentalWithBillingEntry();
    $customer = $rental->customer;

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/chat', [
      'message' => "Faturar ciclos pendentes da {$customer->nome}",
      'mode' => 'agent',
    ])
      ->assertOk()
      ->assertJsonPath('requires_confirmation', true)
      ->assertJsonPath('command', 'billing.process_customer_pending')
      ->assertJsonPath('dry_run_preview.ok', true)
      ->assertJsonPath('dry_run_preview.dry_run', true);
  }

  public function test_heuristic_parser_detects_customer_search(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Buscar cliente João Silva');

    $this->assertSame('customer.search', $parsed['command'] ?? null);
    $this->assertSame('João Silva', $parsed['input']['q'] ?? null);
  }

  public function test_chat_api_returns_confirmation_for_write_command(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/chat', [
      'message' => 'Registrar retorno da LOC-000099',
      'mode' => 'agent',
    ])
      ->assertOk()
      ->assertJsonPath('requires_confirmation', true)
      ->assertJsonPath('command', 'rental.return')
      ->assertJsonStructure(['session_id']);
  }

  public function test_copilot_floating_panel_loads_for_gestor(): void
  {
    $this->actingAs($this->agentUser());

    Livewire::test(CopilotPanel::class)
      ->assertSee('Abrir copiloto')
      ->call('togglePanel')
      ->assertSet('isOpen', true)
      ->assertSee('Pergunta')
      ->assertSee('Agente');
  }

  public function test_copilot_tracks_page_context_on_rental_show(): void
  {
    $user = $this->agentUser();
    $rental = $this->createRentalWithBillingEntry();

    $this->actingAs($user);

    Livewire::withQueryParams([])
      ->test(CopilotPanel::class)
      ->call('syncPageContext', route('rentals.show', $rental))
      ->assertSet('pageRoute', 'rentals.show')
      ->assertSet('pageLabel', 'Ficha da locação')
      ->assertSet('pageDetail', $rental->codigo);
  }

  public function test_legacy_copilot_route_redirects_to_dashboard(): void
  {
    $this->actingAs($this->agentUser())
      ->get('/copiloto')
      ->assertRedirect('/dashboard?copilot=1');
  }

  public function test_finance_summary_via_chat_executes_immediately(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/chat', [
      'message' => 'resumo financeiro',
    ])
      ->assertOk()
      ->assertJsonPath('executed', true)
      ->assertJsonPath('result.ok', true);

    $this->assertSame(1, AgentSession::query()->count());
    $this->assertGreaterThanOrEqual(2, AgentMessage::query()->count());
  }

  public function test_chat_logs_command_on_execution(): void
  {
    Sanctum::actingAs($this->agentUser());

    $this->postJson('/api/agent/chat', [
      'message' => 'resumo financeiro',
    ])->assertOk();

    $this->assertSame(1, AgentCommandLog::query()->where('command', 'finance.summary')->count());
  }

  public function test_invoice_dry_run_preview_on_confirmation(): void
  {
    $user = $this->agentUser();
    $rental = $this->createRentalWithBillingEntry();
    $entry = $rental->billingQueueEntries()->first();

    Sanctum::actingAs($user);

    $this->postJson('/api/agent/chat', [
      'message' => "Faturar {$entry->codigo}",
      'mode' => 'agent',
    ])
      ->assertOk()
      ->assertJsonPath('requires_confirmation', true)
      ->assertJsonPath('dry_run_preview.ok', true)
      ->assertJsonPath('dry_run_preview.dry_run', true);

    $this->assertSame(1, AgentCommandLog::query()->where('dry_run', true)->count());
  }

  public function test_agent_log_admin_page_loads(): void
  {
    $user = $this->agentUser();
    $user->givePermissionTo('audit.view');
    $this->actingAs($user);

    AgentCommandLog::create([
      'user_id' => $user->id,
      'command' => 'finance.summary',
      'input' => [],
      'result' => ['ok' => true, 'message' => 'Teste'],
      'dry_run' => false,
      'ok' => true,
      'created_at' => now(),
    ]);

    Livewire::test(AgentLogIndex::class)
      ->assertSee('Copiloto — auditoria')
      ->assertSee('finance.summary');
  }

  private function agentUser(): User
  {
    $user = User::factory()->create(['ativo' => true]);
    $user->assignRole(UserRole::Gestor->value);
    $user->givePermissionTo('agent.api');

    return $user;
  }

  private function createRentalWithBillingEntry(): Rental
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $customer = Customer::create([
      'nome' => 'Cliente Billing',
      'cpf_cnpj' => '39053344705',
      'ativo' => true,
    ]);

    $category = EquipmentCategory::create([
      'nome' => 'Cat',
      'tipo_linha' => 'linha_leve',
      'ativo' => true,
    ]);

    $model = EquipmentModel::create([
      'equipment_category_id' => $category->id,
      'marca' => 'M',
      'modelo' => 'X',
      'ativo' => true,
    ]);

    $asset = app(AssetStatusService::class)->createWithInitialStatus(new Asset([
      'codigo_patrimonio' => 'PAT-BILL-1',
      'equipment_model_id' => $model->id,
      'localizacao' => 'Pátio',
    ]), AssetStatus::Disponivel);

    $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));

    RentalBillingQueueEntry::create([
      'codigo' => 'FAT-000001',
      'rental_id' => $rental->id,
      'customer_id' => $customer->id,
      'tipo' => RentalBillingQueueType::Locacao->value,
      'periodo_inicio' => now()->toDateString(),
      'periodo_fim' => now()->addDays(28)->toDateString(),
      'valor_nf' => 500,
      'valor_car' => 500,
      'status' => RentalBillingQueueStatus::Pendente->value,
      'gerado_em' => now(),
    ]);

    return $rental->fresh(['billingQueueEntries']);
  }
}
