<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Layout\GlobalSearch;
use App\Livewire\Layout\GlobalSearchResults;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\GlobalSearchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_global_search_finds_category_with_plural_term(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Martelete');
        $model = $this->createModel($category, 'Bosch', 'GBH 2-24');

        $this->createAsset($model, 'PAT-MAR-001', AssetStatus::Disponivel);
        $this->createAsset($model, 'PAT-MAR-002', AssetStatus::Locado);

        $categories = app(GlobalSearchService::class)->matchingCategories('marteletes');

        $this->assertCount(1, $categories);
        $this->assertSame('Martelete', $categories->first()->nome);
    }

    public function test_global_search_results_lists_all_category_assets_with_smart_links(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Martelete');
        $model = $this->createModel($category, 'Bosch', 'GBH 2-24');
        $customer = Customer::create([
            'nome' => 'Cliente Busca',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $disponivel = $this->createAsset($model, 'PAT-MAR-010', AssetStatus::Disponivel);
        $locado = $this->createAsset($model, 'PAT-MAR-011', AssetStatus::Locado);

        Rental::create([
            'codigo' => 'LOC-BUSCA-1',
            'asset_id' => $locado->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        $results = app(GlobalSearchService::class)->fullResults('marteletes');

        $this->assertCount(1, $results['categories']);
        $this->assertSame(2, $results['categories']->first()['total']);

        $rows = $results['categories']->first()['assets'];
        $disponivelRow = $rows->firstWhere('codigo_patrimonio', 'PAT-MAR-010');
        $locadoRow = $rows->firstWhere('codigo_patrimonio', 'PAT-MAR-011');

        $this->assertSame(route('assets.show', $disponivel), $disponivelRow['primary_url']);
        $this->assertSame('Ficha do patrimônio', $disponivelRow['primary_label']);
        $this->assertSame(route('rentals.show', Rental::where('codigo', 'LOC-BUSCA-1')->first()), $locadoRow['rental_url']);
        $this->assertSame(route('assets.show', $locado), $locadoRow['asset_url']);
    }

    public function test_global_search_submit_redirects_to_results_page_for_category(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Betoneira');
        $model = $this->createModel($category, 'Menegotti', '400L');
        $this->createAsset($model, 'PAT-BET-001', AssetStatus::Disponivel);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'betoneiras')
            ->call('submit')
            ->assertRedirect(route('search.results', ['q' => 'betoneiras']));
    }

    public function test_global_search_submit_redirects_directly_for_exact_asset_code(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Gerador');
        $model = $this->createModel($category, 'Honda', 'EG 6500');
        $asset = $this->createAsset($model, 'PAT-GER-099', AssetStatus::Disponivel);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'PAT-GER-099')
            ->call('submit')
            ->assertRedirect(route('assets.show', $asset));
    }

    public function test_global_search_finds_rental_by_contract_number(): void
    {
        $this->user();
        $category = $this->createCategory('Compactador');
        $model = $this->createModel($category, 'Wacker', 'DPU 6555');
        $asset = $this->createAsset($model, 'PAT-COMP-01', AssetStatus::Locado);
        $customer = Customer::create([
            'nome' => 'Cliente Contrato',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000123',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        $byFullCode = app(GlobalSearchService::class)->fullResults('LOC-000123');
        $byNumeric = app(GlobalSearchService::class)->fullResults('123');

        $this->assertCount(1, $byFullCode['rentals']);
        $this->assertSame('LOC-000123', $byFullCode['rentals']->first()['codigo']);
        $this->assertCount(1, $byNumeric['rentals']);
        $this->assertSame(route('rentals.show', $rental), $byNumeric['rentals']->first()['url']);
    }

    public function test_global_search_submit_redirects_directly_for_exact_rental_code(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Rolo');
        $model = $this->createModel($category, 'Dynapac', 'CC1200');
        $asset = $this->createAsset($model, 'PAT-ROL-01', AssetStatus::Reservado);
        $customer = Customer::create([
            'nome' => 'Cliente Redirect',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000456',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'LOC-000456')
            ->call('submit')
            ->assertRedirect(route('rentals.show', $rental));
    }

    public function test_global_search_submit_redirects_for_numeric_contract_without_prefix(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Escavadeira');
        $model = $this->createModel($category, 'CAT', '320');
        $asset = $this->createAsset($model, 'PAT-ESC-01', AssetStatus::Reservado);
        $customer = Customer::create([
            'nome' => 'Cliente Numérico',
            'cpf_cnpj' => '15350946056',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-000789',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', '789')
            ->call('submit')
            ->assertRedirect(route('rentals.show', $rental));
    }

    public function test_global_search_dropdown_lists_contract_suggestions(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Guincho');
        $model = $this->createModel($category, 'Iveco', 'Daily');
        $asset = $this->createAsset($model, 'PAT-GUI-01', AssetStatus::Locado);
        $customer = Customer::create([
            'nome' => 'Cliente Dropdown',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        Rental::create([
            'codigo' => 'LOC-000321',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'LOC-000321')
            ->assertSee('LOC-000321')
            ->assertSee('Cliente Dropdown')
            ->assertSee('contrato');
    }

    public function test_global_search_dropdown_shows_contract_and_asset_for_rented_patrimony(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Vibrador');
        $model = $this->createModel($category, 'Wacker', 'M1500');
        $asset = $this->createAsset($model, 'PAT-0049', AssetStatus::Locado);
        $customer = Customer::create([
            'nome' => 'Cliente Patrimônio',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        Rental::create([
            'codigo' => 'LOC-000048',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', '0049')
            ->assertSee('LOC-000048')
            ->assertSee('PAT-0049')
            ->assertSee('contrato')
            ->assertSee('patrimonio')
            ->assertSee('Ficha do contrato')
            ->assertSee('Ficha do patrimônio');
    }

    public function test_global_search_submit_shows_results_when_rented_patrimony_has_two_destinations(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Placa');
        $model = $this->createModel($category, 'Wacker', 'DPU');
        $asset = $this->createAsset($model, 'PAT-0049', AssetStatus::Locado);
        $customer = Customer::create([
            'nome' => 'Cliente Duas Opções',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        Rental::create([
            'codigo' => 'LOC-000048',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(GlobalSearch::class)
            ->set('query', 'PAT-0049')
            ->call('submit')
            ->assertRedirect(route('search.results', ['q' => 'PAT-0049']));
    }

    public function test_global_search_results_page_renders_category_table(): void
    {
        $user = $this->user();
        $category = $this->createCategory('Andaime');
        $model = $this->createModel($category, 'Metálica', 'Torre 2m');
        $this->createAsset($model, 'PAT-AND-001', AssetStatus::Disponivel);

        $this->actingAs($user)
            ->get(route('search.results', ['q' => 'andaimes']))
            ->assertOk()
            ->assertSee('Andaime')
            ->assertSee('PAT-AND-001')
            ->assertSee('Ficha do patrimônio');
    }

    private function user(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function createCategory(string $nome): EquipmentCategory
    {
        return EquipmentCategory::create([
            'nome' => $nome,
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);
    }

    private function createModel(EquipmentCategory $category, string $marca, string $modelo): EquipmentModel
    {
        return EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => $marca,
            'modelo' => $modelo,
            'ativo' => true,
        ]);
    }

    private function createAsset(EquipmentModel $model, string $code, AssetStatus $status): Asset
    {
        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio A',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
