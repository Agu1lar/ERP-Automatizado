<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use App\Enums\RentalPricingPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RolePermissionSeeder::class);
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
            ->assertJsonPath('version', '1.0')
            ->assertJsonFragment(['name' => 'rental.get'])
            ->assertJsonFragment(['name' => 'rental.return'])
            ->assertJsonFragment(['name' => 'maintenance.open'])
            ->assertJsonFragment(['name' => 'receivable.mark_paid'])
            ->assertJsonFragment(['name' => 'customer.search'])
            ->assertJsonFragment(['name' => 'billing.list_pending']);
    }

  public function test_agent_manifest_artisan_command_outputs_json(): void
  {
    $this->artisan('agent:manifest')
      ->assertSuccessful();
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

  private function agentUser(): User
  {
    $user = $this->user(UserRole::Gestor);
    $user->givePermissionTo('agent.api');

    return $user;
  }

  private function user(UserRole $role): User
  {
    $user = User::factory()->create(['ativo' => true]);
    $user->assignRole($role->value);

    return $user;
  }

  private function customer(): Customer
  {
    return Customer::create([
      'nome' => 'Cliente Agente',
      'cpf_cnpj' => '39053344705',
      'ativo' => true,
    ]);
  }

  private function asset(string $code, AssetStatus $status): Asset
  {
    $category = EquipmentCategory::create([
      'nome' => 'Agente',
      'tipo_linha' => 'linha_leve',
      'ativo' => true,
    ]);

    $model = EquipmentModel::create([
      'equipment_category_id' => $category->id,
      'marca' => 'Marca',
      'modelo' => 'Modelo',
      'ativo' => true,
    ]);

    $asset = new Asset([
      'codigo_patrimonio' => $code,
      'equipment_model_id' => $model->id,
      'localizacao' => 'Pátio',
    ]);

    return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
  }

  private function reservedRental(): Rental
  {
    $user = $this->agentUser();
    $this->actingAs($user);

    $customer = $this->customer();
    $asset = $this->asset('PAT-AGENT-CTX', AssetStatus::Disponivel);

    return app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));
  }
}
