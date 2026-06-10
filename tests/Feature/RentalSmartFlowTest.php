<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use App\Support\UserHierarchy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalSmartFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reserve_prefills_rental_ficha_from_asset(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $asset = $this->createAsset(AssetStatus::Disponivel, [
            'descricao' => 'Escavadeira hidráulica revisada',
            'horimetro' => 1250.5,
            'serie' => 'SN-99',
        ]);
        $customer = Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $this->actingAs($admin);

        $rental = app(RentalService::class)->reserve($asset, $customer);

        $this->assertEquals(1250.5, (float) $rental->horimetro_saida);
        $this->assertEquals('Escavadeira hidráulica revisada', $rental->ficha_descricao);
    }

    public function test_operational_role_can_create_rentals_and_customers(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);

        $this->assertTrue(UserHierarchy::isOperational($comercial));
        $this->assertTrue(UserHierarchy::canCreateRecords($comercial));
        $this->assertTrue($comercial->can('rentals.reserve'));
        $this->assertTrue($comercial->can('customers.manage'));
    }

    public function test_comercial_can_create_rental_by_pasting_asset_code(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);
        $asset = $this->createAsset(AssetStatus::Disponivel, [
            'codigo_patrimonio' => 'PAT-SMART-01',
            'horimetro' => 500,
        ]);
        $customer = Customer::create([
            'nome' => 'Obra Centro',
            'cpf_cnpj' => '11222333000181',
            'ativo' => true,
        ]);

        $this->actingAs($comercial);

        Livewire::test(\App\Livewire\Rental\RentalIndex::class)
            ->call('openReserveForm')
            ->set('asset_search', 'PAT-SMART-01')
            ->assertSet('asset_id', $asset->id)
            ->set('customer_search', 'Obra Centro')
            ->assertSet('customer_id', $customer->id)
            ->call('saveReservation')
            ->assertRedirect();

        $this->assertDatabaseHas('rentals', [
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'horimetro_saida' => 500,
        ]);
    }

    public function test_comercial_can_create_customer_inline_during_rental(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);
        $this->createAsset(AssetStatus::Disponivel, ['codigo_patrimonio' => 'PAT-INLINE']);

        $this->actingAs($comercial);

        Livewire::test(\App\Livewire\Rental\RentalIndex::class)
            ->call('openReserveForm')
            ->set('showQuickCustomer', true)
            ->set('quick_customer_nome', 'Nova Empresa Ltda')
            ->set('quick_customer_cpf_cnpj', '11222333000181')
            ->set('quick_customer_telefone', '11999990000')
            ->call('createQuickCustomer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'nome' => 'Nova Empresa Ltda',
            'cpf_cnpj' => '11222333000181',
        ]);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function createAsset(AssetStatus $status, array $overrides = []): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Teste',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo X',
            'ativo' => true,
        ]);

        $asset = new Asset(array_merge([
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ], $overrides));

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
