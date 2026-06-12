<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\GeocodePrecision;
use App\Enums\GeographicRegion;
use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Geography\GeocodeCache;
use App\Models\Domain\Rental\Rental;
use App\Services\AssetStatusService;
use App\Services\Geocoding\GeocodingService;
use App\Services\RentalService;
use App\Services\RentalWorksiteGeocodingService;
use App\Support\WorksiteMapLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geocoding.enabled' => true,
            'geocoding.driver' => 'nominatim',
            'geocoding.nominatim.user_agent' => 'Test Geocoder',
        ]);
    }

    public function test_nominatim_geocoding_stores_cache_and_rental_coordinates(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '-19.9245000',
                    'lon' => '-43.9352000',
                    'display_name' => 'Rua da Bahia, Belo Horizonte',
                    'type' => 'house',
                    'class' => 'building',
                ],
            ], 200),
        ]);

        $rental = $this->rentalWithAddress('Rua da Bahia, 1200, Belo Horizonte, MG');

        $ok = app(RentalWorksiteGeocodingService::class)->geocodeAndStore($rental);
        $this->assertTrue($ok);

        $rental->refresh();
        $this->assertNotNull($rental->obra_latitude);
        $this->assertNotNull($rental->obra_longitude);
        $this->assertSame(GeocodePrecision::Street->value, $rental->obra_geocode_precision);
        $this->assertSame(1, GeocodeCache::query()->count());

        Http::assertSentCount(1);

        app(RentalWorksiteGeocodingService::class)->geocodeAndStore($rental->fresh());
        Http::assertSentCount(1);
    }

    public function test_worksite_locator_uses_stored_coordinates_without_city_offset(): void
    {
        $rental = $this->rentalWithAddress('Rua Teste, 100, Contagem');
        $rental->update([
            'obra_latitude' => -19.9245,
            'obra_longitude' => -43.9352,
            'obra_geocode_precision' => GeocodePrecision::Street->value,
        ]);

        $position = app(WorksiteMapLocator::class)->locate($rental->fresh());

        $this->assertSame(GeocodePrecision::Street->value, $position['precision']);
        $this->assertEqualsWithDelta(-19.9245, $position['lat'], 0.0001);
        $this->assertEqualsWithDelta(-43.9352, $position['lng'], 0.0001);
    }

    public function test_worksite_locator_uses_cache_when_rental_has_no_coordinates(): void
    {
        $rental = $this->rentalWithAddress('Av. Amazonas, 500, Belo Horizonte');
        $rental->load('customer');

        $query = app(GeocodingService::class)->buildWorksiteQuery(
            $rental->local_obra,
            $rental->customer?->endereco,
        );

        app(GeocodingService::class)->storeCache($query, new \App\Services\Geocoding\GeocodeResult(
            latitude: -19.9200,
            longitude: -43.9400,
            precision: GeocodePrecision::Street,
            provider: 'nominatim',
        ));

        $position = app(WorksiteMapLocator::class)->locate($rental);

        $this->assertSame(GeocodePrecision::Street->value, $position['precision']);
        $this->assertEqualsWithDelta(-19.9200, $position['lat'], 0.0001);
    }

    public function test_update_local_obra_triggers_geocoding(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '-19.8500',
                    'lon' => '-43.9500',
                    'type' => 'road',
                    'class' => 'highway',
                ],
            ], 200),
        ]);

        $rental = $this->rentalWithAddress('Endereço antigo');
        $rental->update(['status' => RentalStatus::Locado->value, 'checkout_at' => now()]);

        app(RentalService::class)->updateLocalObra(
            $rental,
            'Rua dos Tupinambás, 300, Belo Horizonte',
        );

        $rental->refresh();
        $this->assertNotNull($rental->obra_latitude);
        $this->assertNotNull($rental->obra_geocoded_at);
    }

    private function rentalWithAddress(string $localObra): Rental
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'nome' => 'Cliente Geo '.$seq,
            'cpf_cnpj' => str_pad((string) (80000000000 + $seq), 11, '0', STR_PAD_LEFT),
            'endereco' => 'Belo Horizonte, MG',
            'ativo' => true,
        ]);

        $asset = $this->asset('PAT-GEO-'.$seq);

        return Rental::create([
            'codigo' => 'LOC-GEO-'.$seq,
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
            'local_obra' => $localObra,
            'regiao_geografica' => GeographicRegion::Bh->value,
        ]);
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
