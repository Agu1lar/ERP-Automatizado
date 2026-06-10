<?php

namespace Tests\Feature;

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
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3FlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reserve_creates_rental_and_updates_asset_status(): void
    {
        $admin = $this->adminUser();
        $asset = $this->createAsset(AssetStatus::Disponivel);
        $customer = $this->createCustomer();

        $this->actingAs($admin);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3), 'Reserva teste');

        $asset->refresh();

        $this->assertEquals(RentalStatus::Reservado->value, $rental->status);
        $this->assertEquals(AssetStatus::Reservado->value, $asset->status);
        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_checkout_moves_rental_to_locado_with_checklist(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin);

        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);

        app(RentalService::class)->checkout($rental, $checked, 'Saída ok');

        $rental->refresh();
        $rental->asset->refresh();

        $this->assertEquals(RentalStatus::Locado->value, $rental->status);
        $this->assertEquals(AssetStatus::Locado->value, $rental->asset->status);
        $this->assertDatabaseHas('rental_checklists', [
            'rental_id' => $rental->id,
            'tipo' => 'saida',
        ]);
    }

    public function test_return_moves_rental_to_em_inspecao(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createLocadoRental();

        $this->actingAs($admin);

        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);

        app(RentalService::class)->registerReturn($rental, $checked);

        $rental->refresh();
        $rental->asset->refresh();

        $this->assertEquals(RentalStatus::EmInspecao->value, $rental->status);
        $this->assertEquals(AssetStatus::EmInspecao->value, $rental->asset->status);
    }

    public function test_complete_inspection_finishes_rental_and_frees_asset(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createRentalInInspection();

        $this->actingAs($admin);

        app(RentalService::class)->completeInspection($rental);

        $rental->refresh();
        $rental->asset->refresh();

        $this->assertEquals(RentalStatus::Concluido->value, $rental->status);
        $this->assertEquals(AssetStatus::Disponivel->value, $rental->asset->status);
    }

    public function test_cancel_reservation_returns_asset_to_disponivel(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin);

        app(RentalService::class)->cancel($rental, 'Cliente desistiu');

        $rental->refresh();
        $rental->asset->refresh();

        $this->assertEquals(RentalStatus::Cancelado->value, $rental->status);
        $this->assertEquals(AssetStatus::Disponivel->value, $rental->asset->status);
    }

    public function test_checkout_requires_all_checklist_items(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin);

        $this->expectException(\InvalidArgumentException::class);

        app(RentalService::class)->checkout($rental, ['visual_ok' => true]);
    }

    public function test_operational_roles_can_access_and_operate_rentals(): void
    {
        $operacao = User::factory()->create(['ativo' => true]);
        $operacao->assignRole(UserRole::Operacao->value);

        $rental = $this->createReservedRental();

        $this->actingAs($operacao);

        $this->assertTrue($operacao->can('reserve', Rental::class));
        $this->assertTrue($operacao->can('operate', $rental));

        $this->get(route('rentals.show', $rental))->assertOk();
        $this->get(route('rentals.index'))->assertOk();
    }

    public function test_rentals_index_page_loads_for_admin(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('rentals.index'))
            ->assertOk();
    }

    public function test_rental_show_page_loads(): void
    {
        $admin = $this->adminUser();
        $rental = $this->createReservedRental();

        $this->actingAs($admin)
            ->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertSee($rental->codigo);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '12345678909',
            'ativo' => true,
        ]);
    }

    private function createAsset(AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Teste',
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
            'codigo_patrimonio' => 'PAT-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }

    private function createReservedRental(): Rental
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        return app(RentalService::class)->reserve(
            $this->createAsset(AssetStatus::Disponivel),
            $this->createCustomer(),
        );
    }

    private function createLocadoRental(): Rental
    {
        $rental = $this->createReservedRental();
        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);

        return app(RentalService::class)->checkout($rental, $checked);
    }

    private function createRentalInInspection(): Rental
    {
        $rental = $this->createLocadoRental();
        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);

        return app(RentalService::class)->registerReturn($rental, $checked);
    }
}
