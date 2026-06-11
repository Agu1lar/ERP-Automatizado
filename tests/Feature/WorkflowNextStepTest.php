<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Livewire\Maintenance\MaintenanceOrderShow;
use App\Livewire\Rental\RentalIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\MaintenanceOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowNextStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_blocked_customer_reserve_shows_finance_actions(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = Customer::create([
            'nome' => 'Cliente Bloqueado UX',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
            'bloqueado' => true,
            'motivo_bloqueio' => 'Títulos em atraso',
            'bloqueado_at' => now(),
            'bloqueado_by' => $user->id,
        ]);

        $asset = $this->asset('PAT-UX-1', AssetStatus::Disponivel);

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(RentalIndex::class)
            ->call('openReserveForm')
            ->call('pickAsset', $asset->id)
            ->call('pickCustomer', $customer->id)
            ->set('expected_return_at', now()->addDays(3)->toDateString())
            ->call('saveReservation')
            ->assertHasNoErrors()
            ->assertSee('Cliente bloqueado')
            ->assertSee('Ver cliente');
    }

    public function test_maintenance_start_flashes_next_step_actions(): void
    {
        $user = $this->user(UserRole::Gestor);
        $asset = $this->asset('PAT-OS-1', AssetStatus::EmManutencao);

        $this->actingAs($user);

        $order = app(MaintenanceOrderService::class)->open($asset, 'Teste fluxo');

        Livewire::actingAs($user)
            ->test(MaintenanceOrderShow::class, ['order' => $order])
            ->call('start')
            ->assertHasNoErrors()
            ->assertSee('OS em execução');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'UX',
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
}
