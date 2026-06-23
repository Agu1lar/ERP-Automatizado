<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Reports\MaintenanceCostReportIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceLaborHour;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\MaintenancePart;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\MaintenanceCostReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class MaintenanceCostReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_report_summarizes_os_cost_against_billing(): void
    {
        $asset = $this->asset('PAT-COST-1');
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-COST-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(20),
            'checkout_at' => now()->subDays(19),
            'completed_at' => now()->subDays(3),
            'valor_faturamento' => 2000,
        ]);

        $order = MaintenanceOrder::create([
            'codigo' => 'OS-COST-1',
            'asset_id' => $asset->id,
            'status' => MaintenanceOrderStatus::Concluida->value,
            'tipo' => 'corretiva',
            'prioridade' => 'normal',
            'impeditiva' => false,
            'descricao_problema' => 'Troca de peça',
            'opened_at' => now()->subDays(5),
            'completed_at' => now()->subDays(2),
            'valor_servico_externo' => 100,
        ]);

        MaintenancePart::create([
            'maintenance_order_id' => $order->id,
            'descricao' => 'Filtro',
            'quantidade' => 1,
            'valor_unitario' => 150,
        ]);

        MaintenanceLaborHour::create([
            'maintenance_order_id' => $order->id,
            'data' => now()->subDays(2),
            'horas' => 2,
            'descricao_atividade' => 'Mão de obra',
        ]);

        $summary = app(MaintenanceCostReportService::class)->summary(now()->subMonth(), now());

        $this->assertSame(2000.0, $summary['faturamento']);
        $this->assertGreaterThan(150.0, $summary['custo_os']);
        $this->assertSame(100.0, $summary['custo_externo']);
    }

    public function test_report_groups_cost_by_category(): void
    {
        $catA = EquipmentCategory::create(['nome' => 'Betoneiras', 'tipo_linha' => 'linha_leve', 'ativo' => true]);
        $catB = EquipmentCategory::create(['nome' => 'Compactadores', 'tipo_linha' => 'linha_leve', 'ativo' => true]);

        $assetA = $this->asset('PAT-CAT-A', $catA->id);
        $assetB = $this->asset('PAT-CAT-B', $catB->id);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-CAT-A',
            'asset_id' => $assetA->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(10),
            'checkout_at' => now()->subDays(9),
            'completed_at' => now()->subDays(2),
            'valor_faturamento' => 3000,
        ]);

        Rental::create([
            'codigo' => 'LOC-CAT-B',
            'asset_id' => $assetB->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(8),
            'checkout_at' => now()->subDays(7),
            'completed_at' => now()->subDays(1),
            'valor_faturamento' => 500,
        ]);

        $orderA = MaintenanceOrder::create([
            'codigo' => 'OS-CAT-A',
            'asset_id' => $assetA->id,
            'status' => MaintenanceOrderStatus::Concluida->value,
            'tipo' => 'corretiva',
            'prioridade' => 'normal',
            'impeditiva' => false,
            'descricao_problema' => 'Manutenção betoneira',
            'opened_at' => now()->subDays(4),
            'completed_at' => now()->subDays(1),
            'valor_servico_externo' => 200,
        ]);

        MaintenancePart::create([
            'maintenance_order_id' => $orderA->id,
            'descricao' => 'Peça',
            'quantidade' => 1,
            'valor_unitario' => 300,
        ]);

        $rows = app(MaintenanceCostReportService::class)->byCategory(now()->subMonth(), now());
        $betoneiras = $rows->firstWhere('grupo_nome', 'Betoneiras');
        $compactadores = $rows->firstWhere('grupo_nome', 'Compactadores');

        $this->assertNotNull($betoneiras);
        $this->assertSame(3000.0, $betoneiras->faturamento);
        $this->assertGreaterThan(400.0, $betoneiras->custo_os);
        $this->assertSame(200.0, $betoneiras->custo_externo);

        $this->assertNotNull($compactadores);
        $this->assertSame(500.0, $compactadores->faturamento);
        $this->assertSame(0.0, $compactadores->custo_os);
    }

    public function test_report_page_shows_category_tab(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);

        $this->actingAs($user);

        Livewire::test(MaintenanceCostReportIndex::class)
            ->assertSee('Por categoria')
            ->set('tab', 'categoria');
    }

    public function test_report_page_loads_for_gestor(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);

        $this->actingAs($user);

        Livewire::test(MaintenanceCostReportIndex::class)
            ->assertSee('Custo de OS vs faturamento')
            ->assertSee('Faturamento');
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Custo',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, ?int $categoryId = null): Asset
    {
        if ($categoryId === null) {
            $category = EquipmentCategory::create([
                'nome' => 'Custo',
                'tipo_linha' => 'linha_leve',
                'ativo' => true,
            ]);
            $categoryId = $category->id;
        }

        $model = EquipmentModel::create([
            'equipment_category_id' => $categoryId,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
