<?php

namespace Tests\Feature;

use App\Agent\AgentCommandExecutor;
use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('agent')]
class AgentFleetAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_fleet_analytics_command_returns_occupancy(): void
    {
        $user = $this->agentUser();
        $asset = $this->asset('PAT-AG-FLEET');
        $customer = Customer::create(['nome' => 'C', 'cpf_cnpj' => '39053344705', 'ativo' => true]);

        Rental::create([
            'codigo' => 'LOC-AG-FLEET',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(5),
            'checkout_at' => now()->subDays(4),
            'expected_return_at' => now()->addDays(2),
        ]);

        $result = app(AgentCommandExecutor::class)->execute(
            'fleet.analytics',
            ['view' => 'occupancy', 'group_by' => 'asset'],
            $user,
        );

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('ocupação', mb_strtolower($result->message));
        $this->assertSame('fleet_analytics', $result->data['entity'] ?? null);
    }

    public function test_manifest_lists_fleet_analytics_command(): void
    {
        Sanctum::actingAs($this->agentUser());

        $this->getJson('/api/agent/manifest')
            ->assertOk()
            ->assertJsonFragment(['name' => 'fleet.analytics']);
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Frota Agent',
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
            'valor_compra' => 5000,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
