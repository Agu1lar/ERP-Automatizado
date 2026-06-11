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
use App\Livewire\Copilot\CopilotIndex;
use Tests\TestCase;

class AgentCopilotTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RolePermissionSeeder::class);
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

  public function test_heuristic_parser_detects_billing_list_pending(): void
  {
    $parsed = app(AgentHeuristicParser::class)->parse('Mostrar pendências a faturar');

    $this->assertSame('billing.list_pending', $parsed['command'] ?? null);
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
    ])
      ->assertOk()
      ->assertJsonPath('requires_confirmation', true)
      ->assertJsonPath('command', 'rental.return')
      ->assertJsonStructure(['session_id']);
  }

  public function test_copilot_page_loads_for_gestor(): void
  {
    $this->actingAs($this->agentUser());

    Livewire::test(CopilotIndex::class)
      ->assertSee('Copiloto')
      ->assertSee('resumo financeiro');
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
