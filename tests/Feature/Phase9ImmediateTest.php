<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceOrderStatus;
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
use App\Services\MaintenanceOrderService;
use App\Services\RentalService;
use App\Support\MaintenancePanelQuery;
use App\Support\RentalPanelQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Phase9ImmediateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_overdue_returns_scope_and_panel_filter(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Marteletes');

        $overdueAsset = $this->asset($model, 'PAT-OVER', AssetStatus::Disponivel);
        $okAsset = $this->asset($model, 'PAT-OK', AssetStatus::Disponivel);

        $this->actingAs($user);

        $overdue = app(RentalService::class)->reserve($overdueAsset, $customer, now()->addDays(5));
        app(RentalService::class)->checkout($overdue, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $overdue->update([
            'reserved_at' => now()->subDays(10),
            'expected_return_at' => now()->subDays(2)->toDateString(),
        ]);

        $onTime = app(RentalService::class)->reserve($okAsset, $customer, now()->addDays(5));
        app(RentalService::class)->checkout($onTime, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $onTime->update(['expected_return_at' => now()->addDays(3)]);

        $this->assertTrue($overdue->fresh()->isReturnOverdue());
        $this->assertFalse($onTime->fresh()->isReturnOverdue());
        $this->assertSame(1, Rental::query()->overdueReturns()->count());

        $filtered = app(RentalPanelQuery::class)->apply([
            'status_scope' => 'locado',
            'overdue_only' => true,
            'sort_by' => 'retorno',
            'sort_dir' => 'asc',
        ])->pluck('codigo')->all();

        $this->assertSame([$overdue->codigo], $filtered);
    }

    public function test_customer_show_page_displays_history_and_active_rentals(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Betoneiras');
        $asset = $this->asset($model, 'PAT-CUST', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(4));
        app(RentalService::class)->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $rental->update(['valor_faturamento' => 500, 'expected_return_at' => now()->addDay()]);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->nome)
            ->assertSee($rental->codigo)
            ->assertSee('Locações ativas');

        Livewire::test(\App\Livewire\Customer\CustomerShow::class, ['customer' => $customer])
            ->assertSee('Faturamento total')
            ->assertSee('R$ 0,00');
    }

    public function test_commercial_report_csv_export(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $model = $this->model('Geradores');
        $asset = $this->asset($model, 'PAT-CSV', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDay());
        app(RentalService::class)->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        app(RentalService::class)->registerReturn($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true));
        app(RentalService::class)->completeInspection($rental, false);
        $rental->update(['valor_faturamento' => 1200, 'completed_at' => now()]);

        $response = $this->get(route('reports.commercial.export', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'group_by' => 'model',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Relatório comercial', $response->streamedContent());
        $this->assertStringContainsString('1.200,00', $response->streamedContent());
    }

    public function test_maintenance_panel_shows_open_orders_by_column(): void
    {
        $user = $this->user(UserRole::Manutencao);
        $model = $this->model('Andaimes');
        $asset = $this->asset($model, 'PAT-OS', AssetStatus::Disponivel);

        $this->actingAs($user);

        $open = app(MaintenanceOrderService::class)->open($asset, 'Motor com ruído', user: $user);
        $open->update(['expected_completion_at' => now()->subDay()]);

        $execAsset = $this->asset($model, 'PAT-OS2', AssetStatus::Disponivel);
        $executing = app(MaintenanceOrderService::class)->open($execAsset, 'Troca de correia', user: $user);
        $executing->update(['status' => MaintenanceOrderStatus::EmExecucao->value, 'started_at' => now()]);

        $columns = app(MaintenancePanelQuery::class)->boardColumns([]);

        $this->assertSame(1, $columns[MaintenanceOrderStatus::Aberta->value]->count());
        $this->assertSame(1, $columns[MaintenanceOrderStatus::EmExecucao->value]->count());
        $this->assertSame(1, $columns['atrasadas']->count());

        Livewire::test(\App\Livewire\Maintenance\MaintenanceOrderIndex::class)
            ->assertSet('activeView', 'painel')
            ->assertSee('Painel operacional')
            ->assertSee($open->codigo)
            ->assertSee($executing->codigo);
    }

    public function test_dashboard_shows_overdue_returns_alert(): void
    {
        $user = $this->user(UserRole::Gestor);
        $customer = $this->customer();
        $model = $this->model('Outros');
        $asset = $this->asset($model, 'PAT-DASH', AssetStatus::Disponivel);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(5));
        app(RentalService::class)->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $rental->update([
            'reserved_at' => now()->subDays(10),
            'expected_return_at' => now()->subDays(2)->toDateString(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Retornos atrasados')
            ->assertSee($rental->codigo);
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
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '52998224725',
            'telefone' => '(11) 99999-0000',
            'ativo' => true,
        ]);
    }

    private function model(string $categoryName): EquipmentModel
    {
        $category = EquipmentCategory::create([
            'nome' => $categoryName,
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        return EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo X',
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
}
