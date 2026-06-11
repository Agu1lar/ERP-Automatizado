<?php

namespace Tests\Feature\Integration;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fluxo diário crítico: locação → retorno → inspeção → (opcional) OS automática.
 *
 * @group integration
 */
class RentalMaintenanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_full_cycle_without_maintenance_frees_asset(): void
    {
        $user = $this->operator();
        $asset = $this->asset('PAT-FULL-OK', AssetStatus::Disponivel, 'Pátio Central');
        $customer = $this->customer();

        $this->actingAs($user);
        $service = app(RentalService::class);

        $rental = $service->reserve($asset, $customer, now()->addDays(5), localObra: 'Obra Savassi');
        $this->assertSame(RentalStatus::Reservado->value, $rental->status);
        $this->assertSame(AssetStatus::Reservado->value, $asset->fresh()->status);

        $rental = $service->checkout($rental, $this->saidaChecklist());
        $this->assertSame(RentalStatus::Locado->value, $rental->status);
        $this->assertSame(AssetStatus::Locado->value, $rental->asset->status);
        $this->assertSame('Obra Savassi', $rental->asset->fresh()->localizacao);

        $rental = $service->registerReturn($rental, $this->retornoChecklist());
        $this->assertSame(RentalStatus::EmInspecao->value, $rental->status);
        $this->assertSame(AssetStatus::EmInspecao->value, $rental->asset->status);

        $rental = $service->completeInspection($rental, sendToMaintenance: false);
        $this->assertSame(RentalStatus::Concluido->value, $rental->status);
        $this->assertSame(AssetStatus::Disponivel->value, $rental->asset->fresh()->status);
        $this->assertSame('Pátio Central', $rental->asset->fresh()->localizacao);
        $this->assertSame(0, MaintenanceOrder::query()->where('rental_id', $rental->id)->count());
    }

    public function test_return_inspection_opens_maintenance_order_linked_to_rental(): void
    {
        $user = $this->operator();
        $asset = $this->asset('PAT-FULL-OS', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($user);
        $service = app(RentalService::class);

        $rental = $service->reserve($asset, $customer, now()->addDays(3));
        $rental = $service->checkout($rental, $this->saidaChecklist());
        $rental = $service->registerReturn($rental, $this->retornoChecklist(), 'Martelo com trinca no cabo');

        $motivo = 'Revisão pós-retorno — trinca no cabo';
        $rental = $service->completeInspection($rental, sendToMaintenance: true, motivoManutencao: $motivo);

        $rental->refresh();
        $asset->refresh();

        $this->assertSame(RentalStatus::Concluido->value, $rental->status);
        $this->assertSame(AssetStatus::EmManutencao->value, $asset->status);

        $order = MaintenanceOrder::query()->where('rental_id', $rental->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(MaintenanceOrderStatus::Aberta->value, $order->status);
        $this->assertSame(MaintenanceOrderType::RetornoLocacao->value, $order->tipo);
        $this->assertSame($motivo, $order->descricao_problema);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($asset->id, $order->asset_id);
    }

    public function test_inspection_without_maintenance_does_not_leave_open_order(): void
    {
        $user = $this->operator();
        $asset = $this->asset('PAT-NO-OS', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($user);
        $service = app(RentalService::class);

        $rental = $service->reserve($asset, $customer);
        $rental = $service->checkout($rental, $this->saidaChecklist());
        $rental = $service->registerReturn($rental, $this->retornoChecklist());
        $service->completeInspection($rental, sendToMaintenance: false);

        $this->assertSame(0, MaintenanceOrder::query()->open()->where('asset_id', $asset->id)->count());
    }

    public function test_complete_inspection_requires_motivo_when_sending_to_maintenance(): void
    {
        $user = $this->operator();
        $asset = $this->asset('PAT-MOTIVO', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($user);
        $service = app(RentalService::class);

        $rental = $service->reserve($asset, $customer);
        $rental = $service->checkout($rental, $this->saidaChecklist());
        $rental = $service->registerReturn($rental, $this->retornoChecklist());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Motivo obrigatório');

        $service->completeInspection($rental, sendToMaintenance: true, motivoManutencao: null);
    }

    public function test_checklists_are_persisted_through_full_flow(): void
    {
        $user = $this->operator();
        $asset = $this->asset('PAT-CHK', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($user);
        $service = app(RentalService::class);

        $rental = $service->reserve($asset, $customer);
        $rental = $service->checkout($rental, $this->saidaChecklist(), 'Saída conferida');
        $rental = $service->registerReturn($rental, $this->retornoChecklist(), 'Retorno com avaria leve');
        $rental = $service->completeInspection($rental, sendToMaintenance: true, motivoManutencao: 'Avaria leve');

        $rental->load('checklists.items');

        $this->assertCount(2, $rental->checklists);
        $this->assertTrue(
            $rental->checklists->every(
                fn ($checklist) => $checklist->items->every(fn ($item) => $item->checked)
            )
        );
    }

    /** @return array<string, bool> */
    private function saidaChecklist(): array
    {
        return array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);
    }

    /** @return array<string, bool> */
    private function retornoChecklist(): array
    {
        return array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);
    }

    private function operator(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Operacao->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Construtora Integração',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status, string $localizacao = 'Pátio'): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Integração',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Bosch',
            'modelo' => 'GBH 2-24',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => $localizacao,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
