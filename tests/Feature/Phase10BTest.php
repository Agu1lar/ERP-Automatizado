<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Rental\RentalShow;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\ProfitabilityReportService;
use App\Services\RentalService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class Phase10BTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_substitute_asset_preserves_rental_and_updates_assets(): void
    {
        $admin = $this->user(UserRole::Admin);
        $category = $this->category('Martelete');
        $model = $this->model($category);
        $customer = $this->customer();

        $original = $this->asset($model, 'PAT-SUB-01', AssetStatus::Disponivel);
        $replacement = $this->asset($model, 'PAT-SUB-02', AssetStatus::Disponivel);

        $this->actingAs($admin);
        $rental = app(RentalService::class)->reserve($original, $customer, now()->addDays(3));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $rental = app(RentalService::class)->substituteAsset(
            $rental,
            $replacement,
            'Defeito no equipamento original',
        );

        $this->assertSame($replacement->id, $rental->asset_id);
        $this->assertSame(AssetStatus::EmManutencaoCampo->value, $original->fresh()->status);
        $this->assertSame(AssetStatus::Locado->value, $replacement->fresh()->status);
        $this->assertCount(1, $rental->assetSubstitutions);
    }

    public function test_substitute_asset_via_livewire(): void
    {
        $admin = $this->user(UserRole::Admin);
        $model = $this->model($this->category('Betoneira'));
        $customer = $this->customer();
        $original = $this->asset($model, 'PAT-LW-01', AssetStatus::Disponivel);
        $replacement = $this->asset($model, 'PAT-LW-02', AssetStatus::Disponivel);

        $this->actingAs($admin);
        $rental = app(RentalService::class)->reserve($original, $customer, now()->addDays(2));

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->call('openSubstituteModal')
            ->set('substitute_asset_id', (string) $replacement->id)
            ->set('substitute_motivo', 'Troca preventiva')
            ->call('substituteAsset')
            ->assertHasNoErrors();

        $this->assertSame($replacement->id, $rental->fresh()->asset_id);
    }

    public function test_rental_panel_csv_export_respects_filters(): void
    {
        $admin = $this->user(UserRole::Admin);
        $this->actingAs($admin);

        $response = $this->get(route('rentals.panel.export', [
            'status_scope' => 'locado',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_rental_contract_pdf_route_is_accessible(): void
    {
        $admin = $this->user(UserRole::Admin);
        $rental = $this->activeRental($admin);

        $this->actingAs($admin)
            ->get(route('rentals.contract.pdf', $rental))
            ->assertOk();
    }

    public function test_financial_analysis_includes_labor_cost(): void
    {
        $admin = $this->user(UserRole::Admin);
        $asset = $this->asset($this->model($this->category('Gerador')), 'PAT-FIN-1', AssetStatus::Disponivel);
        $customer = $this->customer();

        $this->actingAs($admin);
        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDay());
        app(RentalService::class)->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $rental->update(['valor_faturamento' => 1000, 'status' => RentalStatus::Concluido->value, 'completed_at' => now()]);

        $order = MaintenanceOrder::create([
            'codigo' => 'OS-FIN-1',
            'asset_id' => $asset->id,
            'tipo' => 'corretiva',
            'status' => MaintenanceOrderStatus::Concluida->value,
            'descricao_problema' => 'Revisão geral',
            'opened_at' => now()->subDay(),
            'completed_at' => now(),
        ]);

        $order->laborHours()->create([
            'user_id' => $admin->id,
            'data' => now(),
            'horas' => 2,
            'descricao_atividade' => 'Revisão',
        ]);

        config(['maintenance.default_hourly_rate' => 50.0]);

        $summary = app(ProfitabilityReportService::class)->summary(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(1000.0, $summary['faturamento']);
        $this->assertSame(100.0, $summary['custo_mao_obra']);
        $this->assertSame(100.0, $summary['custo_manutencao']);
    }

    public function test_preventive_due_command_runs(): void
    {
        $this->artisan('maintenance:process-preventive-due', ['--dry-run' => true])
            ->assertSuccessful();
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function category(string $nome): EquipmentCategory
    {
        return EquipmentCategory::create([
            'nome' => $nome,
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);
    }

    private function model(EquipmentCategory $category): EquipmentModel
    {
        return EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);
    }

    private function asset(EquipmentModel $model, string $code, AssetStatus $status): Asset
    {
        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }

    private function customer(): \App\Models\Domain\Customer\Customer
    {
        return \App\Models\Domain\Customer\Customer::create([
            'nome' => 'Cliente 10B',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function activeRental(User $user): Rental
    {
        $this->actingAs($user);
        $asset = $this->asset($this->model($this->category('Andaime')), 'PAT-ACT-1', AssetStatus::Disponivel);

        return app(RentalService::class)->reserve($asset, $this->customer(), now()->addDays(5));
    }
}
