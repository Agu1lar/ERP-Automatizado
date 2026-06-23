<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\MaintenanceOrderService;
use App\Services\ProfitabilityReportService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Fleet\ModelIndex;
use App\Livewire\Reports\FinancialAnalysisIndex;
use Tests\TestCase;


#[Group('livewire')]
class FinancialAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_profitability_service_calculates_revenue_minus_maintenance(): void
    {
        $gestor = $this->user(UserRole::Gestor);
        $asset = $this->asset('PAT-ANA-1');
        $this->actingAs($gestor);

        $rental = Rental::create([
            'codigo' => 'LOC-ANA-1',
            'asset_id' => $asset->id,
            'customer_id' => $this->customer()->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(10),
            'completed_at' => now()->subDays(2),
            'valor_faturamento' => 1000,
        ]);

        $order = app(MaintenanceOrderService::class)->open($asset, 'Troca de peça');
        app(MaintenanceOrderService::class)->addPart($order, 'Peça teste', 1, 'P-1', 150);
        $order->update([
            'status' => MaintenanceOrderStatus::Concluida->value,
            'completed_at' => now()->subDay(),
        ]);

        $from = now()->subMonth();
        $to = now();
        $summary = app(ProfitabilityReportService::class)->summary($from, $to);

        $this->assertSame(1000.0, $summary['faturamento']);
        $this->assertSame(150.0, $summary['custo_pecas']);
        $this->assertSame(0.0, $summary['custo_mao_obra']);
        $this->assertSame(150.0, $summary['custo_manutencao']);
        $this->assertSame(850.0, $summary['resultado']);
    }

    public function test_financial_analysis_page_is_accessible(): void
    {
        $gestor = $this->user(UserRole::Gestor);
        $this->actingAs($gestor);

        Livewire::test(FinancialAnalysisIndex::class)
            ->assertSee('Análise financeira')
            ->assertSee('Faturamento')
            ->assertSee('Resumo consolidado')
            ->assertSee('Total no período')
            ->set('view_mode', 'asset')
            ->assertSee('Patrimônio');
    }

    public function test_model_form_can_create_inline_category(): void
    {
        $admin = $this->user(UserRole::Admin);
        $this->actingAs($admin);

        Livewire::test(ModelIndex::class)
            ->call('create')
            ->call('openInlineCategoryForm')
            ->set('inline_category_nome', 'Categoria Inline')
            ->call('saveInlineCategory')
            ->assertSet('equipment_category_id', EquipmentCategory::query()->where('nome', 'Categoria Inline')->value('id'));
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function customer(): \App\Models\Domain\Customer\Customer
    {
        return \App\Models\Domain\Customer\Customer::create([
            'nome' => 'Cliente Análise',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Análise',
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

        return app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
