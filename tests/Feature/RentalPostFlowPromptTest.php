<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Rental\RentalShow;
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
use Livewire\Livewire;
use Tests\TestCase;

class RentalPostFlowPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_create_maintenance_order_offers_return_to_ficha_prompt(): void
    {
        $user = $this->user(UserRole::Gestor);
        $rental = $this->rentalInStatus(RentalStatus::EmInspecao);

        Livewire::actingAs($user)
            ->test(RentalShow::class, ['rental' => $rental])
            ->call('openMaintenanceOrderModal')
            ->set('os_tipo', MaintenanceOrderType::Corretiva->value)
            ->set('os_descricao', 'Revisão geral')
            ->call('createMaintenanceOrder')
            ->assertHasNoErrors()
            ->assertSet('showPostFlowPrompt', true)
            ->call('stayOnRentalFicha')
            ->assertSet('showPostFlowPrompt', false);
    }

    public function test_go_to_post_flow_destination_flashes_return_link_to_rental(): void
    {
        $user = $this->user(UserRole::Gestor);
        $rental = $this->rentalInStatus(RentalStatus::EmInspecao);

        $component = Livewire::actingAs($user)
            ->test(RentalShow::class, ['rental' => $rental])
            ->call('openMaintenanceOrderModal')
            ->set('os_tipo', MaintenanceOrderType::Corretiva->value)
            ->set('os_descricao', 'Revisão geral')
            ->call('createMaintenanceOrder')
            ->call('goToPostFlowDestination');

        $component->assertRedirect();
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function rentalInStatus(RentalStatus $status): Rental
    {
        $this->actingAs($this->user(UserRole::Gestor));

        $category = EquipmentCategory::create([
            'nome' => 'Prompt',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = app(AssetStatusService::class)->createWithInitialStatus(
            new Asset([
                'codigo_patrimonio' => 'PAT-PROMPT-1',
                'equipment_model_id' => $model->id,
                'localizacao' => 'Pátio',
            ]),
            AssetStatus::Disponivel,
        );

        $customer = Customer::create([
            'nome' => 'Cliente Prompt',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $service = app(RentalService::class);
        $rental = $service->reserve($asset, $customer);
        $rental = $service->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));

        if ($status === RentalStatus::EmInspecao) {
            return $service->registerReturn($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true));
        }

        return $rental;
    }
}
