<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\CommercialReportService;
use App\Services\MaintenanceOrderService;
use App\Services\PreventiveMaintenanceService;
use App\Services\RentalService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class Phase8Priority2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_commercial_report_groups_revenue_by_equipment_model_not_asset(): void
    {
        $marteleteModel = $this->createModel('Marteletes', 'Bosch', 'GBH 2-26');
        $betoneiraModel = $this->createModel('Betoneiras', 'Menegotti', '400L');

        $martelete1 = $this->createAssetForModel($marteleteModel, 'PAT-M1');
        $martelete2 = $this->createAssetForModel($marteleteModel, 'PAT-M2');
        $betoneira = $this->createAssetForModel($betoneiraModel, 'PAT-B1');

        $customer = $this->createCustomer();

        $this->createCompletedRental($martelete1, $customer, 500.00);
        $this->createCompletedRental($martelete2, $customer, 300.00);
        $this->createCompletedRental($betoneira, $customer, 1200.00);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $rows = app(CommercialReportService::class)->revenueByEquipmentType($from, $to, 'model');

        $this->assertCount(2, $rows);

        $marteleteRow = $rows->firstWhere('grupo_nome', 'Bosch GBH 2-26');
        $betoneiraRow = $rows->firstWhere('grupo_nome', 'Menegotti 400L');

        $this->assertNotNull($marteleteRow);
        $this->assertEquals(2, $marteleteRow->total_locacoes);
        $this->assertEquals(800.00, $marteleteRow->faturamento_total);

        $this->assertNotNull($betoneiraRow);
        $this->assertEquals(1, $betoneiraRow->total_locacoes);
        $this->assertEquals(1200.00, $betoneiraRow->faturamento_total);
    }

    public function test_gestor_can_manage_preventive_rules(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $model = $this->createModel('Marteletes', 'Bosch', 'GBH 2-26');

        $this->actingAs($gestor)
            ->get(route('maintenance.preventive.index'))
            ->assertOk();

        Livewire::test(\App\Livewire\Maintenance\PreventiveRuleIndex::class)
            ->call('create')
            ->set('equipment_model_id', (string) $model->id)
            ->set('interval_horas', '250')
            ->set('descricao', 'Troca de escovas e lubrificação')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('preventive_maintenance_rules', [
            'equipment_model_id' => $model->id,
            'interval_horas' => 250,
            'descricao' => 'Troca de escovas e lubrificação',
        ]);
    }

    public function test_preventive_rule_marks_asset_due_by_horimetro(): void
    {
        $model = $this->createModel('Betoneiras', 'Menegotti', '400L');
        $asset = $this->createAssetForModel($model, 'PAT-BET', ['horimetro' => 600]);

        $rule = app(PreventiveMaintenanceService::class)->createRule(
            $model->id,
            250,
            'Revisão geral',
            $this->userWithRole(UserRole::Gestor),
        );

        $status = app(PreventiveMaintenanceService::class)->statusForAssetRule($asset, $rule);

        $this->assertTrue($status['vencida']);
        $this->assertEquals(600.0, $status['horas_desde_ultima']);
    }

    public function test_asset_maintenance_tab_shows_corrective_and_preventive_history(): void
    {
        $gestor = $this->userWithRole(UserRole::Gestor);
        $asset = $this->createAssetForModel(
            $this->createModel('Marteletes', 'Bosch', 'GBH 2-26'),
            'PAT-HIST',
            ['horimetro' => 100],
        );

        $service = app(MaintenanceOrderService::class);
        $this->actingAs($gestor);

        $corretiva = $service->open($asset, 'Motor com ruído', MaintenanceOrderType::Corretiva);
        $service->start($corretiva);
        $service->complete($corretiva, 'Motor reparado');

        $rule = PreventiveMaintenanceRule::create([
            'equipment_model_id' => $asset->equipment_model_id,
            'interval_horas' => 50,
            'descricao' => 'Lubrificação',
            'ativo' => true,
            'created_by' => $gestor->id,
        ]);

        $preventiva = $service->openPreventive($asset->fresh(), $rule);
        $service->start($preventiva);
        $service->complete($preventiva, 'Lubrificação feita');

        $preventiva->refresh();
        $this->assertEquals(100.0, (float) $preventiva->horimetro_servico);

        Livewire::actingAs($gestor)
            ->test(\App\Livewire\Fleet\AssetShow::class, ['asset' => $asset->fresh()])
            ->set('activeTab', 'manutencao')
            ->assertSee('Histórico de manutenção')
            ->assertSee($corretiva->codigo)
            ->assertSee($preventiva->codigo)
            ->assertSee('Corretiva')
            ->assertSee('Preventiva');
    }

    public function test_comercial_user_can_access_commercial_report(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);

        $this->actingAs($comercial)
            ->get(route('reports.commercial'))
            ->assertOk();
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function createModel(string $categoryName, string $marca, string $modelo): EquipmentModel
    {
        $category = EquipmentCategory::create([
            'nome' => $categoryName,
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        return EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => $marca,
            'modelo' => $modelo,
            'ativo' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createAssetForModel(EquipmentModel $model, string $code, array $overrides = []): Asset
    {
        $asset = new Asset(array_merge([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ], $overrides));

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function createCompletedRental(Asset $asset, Customer $customer, float $valor): Rental
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $this->actingAs($admin);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDay());
        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);
        app(RentalService::class)->checkout($rental, $checked);
        $rental->refresh();

        $checkedRetorno = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);
        app(RentalService::class)->registerReturn($rental, $checkedRetorno);
        app(RentalService::class)->completeInspection($rental);

        $rental->update(['valor_faturamento' => $valor]);

        return $rental->fresh();
    }
}
