<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_field_maintenance_keeps_rental_active_after_complete(): void
    {
        $user = $this->maintenanceUser();
        $asset = $this->asset('PAT-CAMPO-1', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($user);
        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $service = app(MaintenanceOrderService::class);
        $order = $service->openField($asset->fresh(), 'Ajuste hidráulico na obra', $rental);

        $this->assertSame(MaintenanceOrderType::Campo->value, $order->tipo);
        $this->assertSame(AssetStatus::EmManutencaoCampo->value, $asset->fresh()->status);

        $service->completeField(
            $order->fresh(),
            array_fill_keys(array_keys(MaintenanceOrderService::CHECKLIST_CAMPO), true),
            'Serviço concluído',
        );

        $asset->refresh();
        $rental->refresh();

        $this->assertSame(AssetStatus::Locado->value, $asset->status);
        $this->assertSame(RentalStatus::Locado->value, $rental->status);
        $this->assertSame(MaintenanceOrderStatus::Concluida->value, $order->fresh()->status);
    }

    public function test_field_scan_page_loads_for_located_asset(): void
    {
        $user = $this->maintenanceUser();
        $asset = $this->asset('PAT-CAMPO-2', AssetStatus::Locado);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-CAMPO-2',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(2),
            'checkout_at' => now()->subDay(),
            'expected_return_at' => now()->addDays(5),
        ]);

        $this->actingAs($user)
            ->get(route('field.maintenance.scan', 'PAT-CAMPO-2'))
            ->assertOk()
            ->assertSee('Manutenção em campo')
            ->assertSee('LOC-CAMPO-2');
    }

    private function maintenanceUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Campo',
            'cpf_cnpj' => '15350946056',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Campo',
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
            'localizacao' => 'Obra',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
