<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use App\Support\RentalPanelQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_panel_shows_locados_sorted_by_expected_return_asc(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Betoneiras');

        $assetA = $this->asset($model, 'PAT-A', AssetStatus::Disponivel);
        $assetB = $this->asset($model, 'PAT-B', AssetStatus::Disponivel);

        $this->actingAs($user);

        $late = app(RentalService::class)->reserve($assetA, $customer, now()->addDays(10));
        app(RentalService::class)->checkout($late, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $late->update(['expected_return_at' => now()->addDays(5)]);

        $soon = app(RentalService::class)->reserve($assetB, $customer, now()->addDays(3));
        app(RentalService::class)->checkout($soon, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $soon->update(['expected_return_at' => now()->addDay()]);

        Livewire::test(\App\Livewire\Rental\RentalIndex::class)
            ->assertSet('activeView', 'painel')
            ->assertSee('Painel locados')
            ->assertSee($soon->codigo)
            ->assertSee($late->codigo);

        $results = app(RentalPanelQuery::class)->apply([
            'status_scope' => 'locado',
            'sort_by' => 'retorno',
            'sort_dir' => 'asc',
        ])->pluck('codigo')->all();

        $this->assertSame([$soon->codigo, $late->codigo], $results);
    }

    public function test_customer_history_shows_all_rentals_for_customer(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Marteletes');
        $asset = $this->asset($model, 'PAT-HIST', AssetStatus::Disponivel);

        $this->actingAs($user);

        $active = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
        app(RentalService::class)->checkout($active, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));

        $completedAsset = $this->asset($model, 'PAT-DONE', AssetStatus::Disponivel);
        $completed = app(RentalService::class)->reserve($completedAsset, $customer, now()->addDay());
        app(RentalService::class)->checkout($completed, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        app(RentalService::class)->registerReturn($completed, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true));
        app(RentalService::class)->completeInspection($completed);

        Livewire::test(\App\Livewire\Rental\RentalIndex::class)
            ->call('pickPanelCustomer', $customer->id)
            ->set('showCustomerHistory', true)
            ->assertSee($active->codigo)
            ->assertSee($completed->codigo)
            ->assertSee('Histórico de '.$customer->nome);
    }

    public function test_panel_filters_by_category_and_valor(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $betoneira = $this->model('Betoneiras');
        $martelete = $this->model('Marteletes');

        $this->actingAs($user);

        $rentalA = $this->locado($this->asset($betoneira, 'PAT-CAT-1', AssetStatus::Disponivel), $customer);
        $rentalA->update(['valor_faturamento' => 500]);

        $rentalB = $this->locado($this->asset($martelete, 'PAT-CAT-2', AssetStatus::Disponivel), $customer);
        $rentalB->update(['valor_faturamento' => 1500]);

        $results = app(RentalPanelQuery::class)->apply([
            'status_scope' => 'locado',
            'category_id' => $betoneira->equipment_category_id,
            'valor_min' => 400,
            'valor_max' => 600,
            'sort_by' => 'valor',
            'sort_dir' => 'asc',
        ])->pluck('id')->all();

        $this->assertSame([$rentalA->id], $results);
    }

    public function test_copilot_deep_link_query_params_apply_panel_filters(): void
    {
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        $category = EquipmentCategory::create([
            'nome' => 'Betoneira',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        Livewire::withQueryParams([
            'aba' => 'painel',
            'escopo' => 'locado',
            'categoria' => (string) $category->id,
        ])
            ->test(\App\Livewire\Rental\RentalIndex::class)
            ->assertSet('activeView', 'painel')
            ->assertSet('panelStatusScope', 'locado')
            ->assertSet('panelCategoryId', (string) $category->id);
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
            'nome' => 'Cliente Painel',
            'cpf_cnpj' => '52998224725',
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

    private function locado(Asset $asset, Customer $customer): Rental
    {
        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));

        return app(RentalService::class)->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
    }
}
