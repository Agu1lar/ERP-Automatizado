<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\GeographicRegion;
use App\Enums\LogisticsDeliveryMode;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Logistics\LogisticsDailyIndex;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\CommercialReportService;
use App\Support\ActiveOperatingCompany;
use App\Support\GeographicRegionClassifier;
use App\Support\LogisticsDailyQuery;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeographicRegionTest extends TestCase
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

    public function test_classifier_detects_bh_rmbh_and_interior(): void
    {
        $classifier = app(GeographicRegionClassifier::class);

        $this->assertSame(GeographicRegion::Bh, $classifier->classify('Obra Savassi — Belo Horizonte'));
        $this->assertSame(GeographicRegion::Rmbh, $classifier->classify('Canteiro Contagem / Industrial'));
        $this->assertSame(GeographicRegion::Interior, $classifier->classify('Obra Uberlândia — MG'));
    }

    public function test_logistics_daily_query_filters_by_region(): void
    {
        $today = Carbon::today();
        $customer = Customer::create(['nome' => 'Cliente Geo', 'cpf_cnpj' => '12345678901', 'ativo' => true]);
        $assetBh = $this->asset('PAT-GEO-BH');
        $assetRmbh = $this->asset('PAT-GEO-RMBH');

        Rental::create([
            'codigo' => 'LOC-GEO-BH',
            'asset_id' => $assetBh->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
            'entrega_agendada_em' => $today,
            'entrega_modalidade' => LogisticsDeliveryMode::EmpresaEntrega->value,
            'local_obra' => 'Belo Horizonte — Centro',
            'regiao_geografica' => GeographicRegion::Bh->value,
        ]);

        Rental::create([
            'codigo' => 'LOC-GEO-RMBH',
            'asset_id' => $assetRmbh->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
            'entrega_agendada_em' => $today,
            'entrega_modalidade' => LogisticsDeliveryMode::EmpresaEntrega->value,
            'local_obra' => 'Contagem — Eldorado',
            'regiao_geografica' => GeographicRegion::Rmbh->value,
        ]);

        $query = app(LogisticsDailyQuery::class);

        $this->assertSame(2, $query->scheduledDeliveries($today)->count());
        $this->assertSame(1, $query->scheduledDeliveries($today, GeographicRegion::Bh->value)->count());
        $this->assertSame(1, $query->scheduledDeliveries($today, GeographicRegion::Rmbh->value)->count());
    }

    public function test_commercial_report_filters_by_region(): void
    {
        $customer = Customer::create(['nome' => 'Cliente Rel', 'cpf_cnpj' => '98765432100', 'ativo' => true]);
        $asset = $this->asset('PAT-REL-1');

        Rental::create([
            'codigo' => 'LOC-REL-BH',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(10),
            'completed_at' => now(),
            'valor_faturamento' => 500,
            'regiao_geografica' => GeographicRegion::Bh->value,
        ]);

        $service = app(CommercialReportService::class);
        $from = now()->subDay();
        $to = now()->addDay();

        $this->assertSame(500.0, $service->totalRevenueInPeriod($from, $to, GeographicRegion::Bh->value));
        $this->assertSame(0.0, $service->totalRevenueInPeriod($from, $to, GeographicRegion::Rmbh->value));
    }

    public function test_logistics_daily_index_region_filter(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Gestor->value);
        $this->actingAs($user);

        Livewire::test(LogisticsDailyIndex::class)
            ->set('regionFilter', GeographicRegion::Bh->value)
            ->assertSet('regionFilter', GeographicRegion::Bh->value);
    }

    private function asset(string $code): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Geo',
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

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
