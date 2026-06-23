<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
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
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class RentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_rental_show_can_open_maintenance_order_linked_to_rental(): void
    {
        $user = $this->operator();
        $rental = $this->rentalInInspection();

        $this->actingAs($user);

        $order = app(\App\Services\MaintenanceOrderService::class)->open(
            $rental->asset,
            'Revisão geral pós-retorno',
            MaintenanceOrderType::Corretiva,
            rental: $rental,
        );

        $this->assertSame($rental->id, $order->rental_id);
        $this->assertSame(MaintenanceOrderType::Corretiva->value, $order->tipo);
        $this->assertSame('Revisão geral pós-retorno', $order->descricao_problema);
    }

    public function test_complete_inspection_with_indemnity_creates_os_and_receivable(): void
    {
        $user = $this->operator();
        $rental = $this->rentalInInspection();
        $rental->update(['valor_faturamento' => 500]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Rental\RentalShow::class, ['rental' => $rental])
            ->call('openCompleteModal')
            ->set('inspectionOutcome', 'indenizacao')
            ->set('motivoManutencao', 'Motor queimado — cobrança de indenização')
            ->set('os_valor_indenizacao', '350')
            ->call('completeInspection')
            ->assertHasNoErrors()
            ->assertSet('showPostFlowPrompt', true);

        $rental->refresh();
        $this->assertSame(RentalStatus::Concluido->value, $rental->status);
        $this->assertSame(850.0, (float) $rental->valor_faturamento);

        $order = MaintenanceOrder::query()->where('rental_id', $rental->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(MaintenanceOrderType::Indenizacao->value, $order->tipo);

        $this->assertDatabaseHas('receivable_titles', [
            'rental_id' => $rental->id,
            'valor' => 350,
        ]);
    }

    private function operator(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Operacao->value);

        return $user;
    }

    private function rentalInInspection(): Rental
    {
        $this->actingAs($this->operator());
        $asset = $this->asset();
        $customer = Customer::create([
            'nome' => 'Cliente Workflow',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $service = app(RentalService::class);
        $rental = $service->reserve($asset, $customer);
        $rental = $service->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $rental = $service->registerReturn($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true));

        return $rental->fresh();
    }

    private function asset(): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Workflow',
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
            'codigo_patrimonio' => 'PAT-WF-01',
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
