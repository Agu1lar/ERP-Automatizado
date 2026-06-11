<?php

namespace Tests\Feature;

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
        $this->assertSame(route('rentals.show', Rental::where('codigo', 'LOC-BUSCA-1')->first()), $locadoRow['primary_url']);
        $this->assertSame('Ficha da locação', $locadoRow['primary_label']);
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
