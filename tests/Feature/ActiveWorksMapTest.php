<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\GeographicRegion;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Logistics\ActiveWorksMapIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Support\ActiveOperatingCompany;
use App\Support\ActiveWorksGeographicQuery;
use App\Support\WorksiteMapLocator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActiveWorksMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $companyId = OperatingCompany::query()->where('slug', 'acesso')->value('id')
            ?? OperatingCompany::query()->orderBy('id')->value('id');

        if ($companyId) {
            ActiveOperatingCompany::set((int) $companyId);
        }
    }

    public function test_worksite_map_page_lists_on_site_rentals(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        $rental = $this->createOnSiteRental('LOC-MAPA-1', 'Belo Horizonte — Savassi', GeographicRegion::Bh);

        $this->actingAs($user)
            ->get(route('logistics.works-map'))
            ->assertOk()
            ->assertSee('Mapa de obras ativas')
            ->assertSee('LOC-MAPA-1')
            ->assertSee('Belo Horizonte');
    }

    public function test_region_filter_limits_results(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        $this->createOnSiteRental('LOC-BH', 'Belo Horizonte — Centro', GeographicRegion::Bh);
        $this->createOnSiteRental('LOC-RMBH', 'Contagem — Eldorado', GeographicRegion::Rmbh);

        Livewire::actingAs($user)
            ->test(ActiveWorksMapIndex::class)
            ->set('regionFilter', GeographicRegion::Bh->value)
            ->assertSee('LOC-BH')
            ->assertDontSee('LOC-RMBH');
    }

    public function test_worksite_locator_uses_city_coordinates(): void
    {
        $rental = $this->createOnSiteRental('LOC-GEO', 'Obra em Contagem, MG', GeographicRegion::Rmbh);

        $position = app(WorksiteMapLocator::class)->locate($rental);

        $this->assertSame('city', $position['precision']);
        $this->assertSame('contagem', $position['city']);
        $this->assertEqualsWithDelta(-19.9320, $position['lat'], 0.05);
        $this->assertEqualsWithDelta(-44.0539, $position['lng'], 0.05);
    }

    public function test_query_excludes_non_locado_rentals(): void
    {
        $this->createOnSiteRental('LOC-ATIVA', 'Belo Horizonte', GeographicRegion::Bh);

        $reserved = $this->createOnSiteRental('LOC-RES', 'Contagem', GeographicRegion::Rmbh);
        $reserved->update(['status' => RentalStatus::Reservado->value]);

        $results = app(ActiveWorksGeographicQuery::class)->onSiteRentals();

        $this->assertCount(1, $results);
        $this->assertSame('LOC-ATIVA', $results->first()->codigo);
    }

    private function createOnSiteRental(string $codigo, string $localObra, GeographicRegion $region): Rental
    {
        static $customerSeq = 0;
        $customerSeq++;
        $customer = Customer::create([
            'nome' => 'Cliente Mapa '.$customerSeq,
            'cpf_cnpj' => str_pad((string) (90000000000 + $customerSeq), 11, '0', STR_PAD_LEFT),
            'ativo' => true,
        ]);
        $asset = $this->asset('PAT-'.$codigo);

        return Rental::create([
            'codigo' => $codigo,
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(5),
            'checkout_at' => now()->subDays(4),
            'local_obra' => $localObra,
            'regiao_geografica' => $region->value,
        ]);
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Mapa',
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
            'localizacao' => 'Obra',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Locado);
    }
}
