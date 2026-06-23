<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\ReceivableTitleStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class FinancialCycleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_shows_receivable_this_week_panel(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();

        ReceivableTitle::create([
            'codigo' => 'TIT-WEEK-1',
            'customer_id' => $customer->id,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor' => 250,
            'vencimento' => now()->startOfWeek()->addDays(2),
            'status' => ReceivableTitleStatus::Aberto->value,
        ]);

        $this->actingAs($user);

        Livewire::test(DashboardIndex::class)
            ->assertSee('A receber esta semana')
            ->assertSee('TIT-WEEK-1')
            ->assertSee('R$ 250,00');
    }

    public function test_dashboard_shows_billing_cycle_due_panel(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $asset = $this->asset('PAT-CYCLE-1', AssetStatus::Locado);

        Rental::create([
            'codigo' => 'LOC-CYCLE-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(30),
            'checkout_at' => now()->subDays(30),
            'next_billing_at' => now()->subDay(),
            'billing_cycle_days' => 28,
        ]);

        $this->actingAs($user);

        Livewire::test(DashboardIndex::class)
            ->assertSee('Ciclos de faturamento vencidos')
            ->assertSee('LOC-CYCLE-1');
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
            'nome' => 'Cliente Dashboard',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Dashboard',
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
