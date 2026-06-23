<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\LogisticsDeliveryMode;
use App\Enums\LogisticsShift;
use App\Enums\LogisticsReturnMode;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Fleet\AssetShow;
use App\Enums\DeliveryManifestStatus;
use App\Livewire\Logistics\DeliveryManifestShow;
use App\Livewire\Logistics\LogisticsDailyIndex;
use App\Livewire\Rental\RentalShow;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Services\DeliveryManifestService;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Support\ActiveOperatingCompany;
use App\Support\LogisticsDailyQuery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class LogisticsTest extends TestCase
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

    public function test_yard_crud_and_asset_origin_yard(): void
    {
        $user = $this->user(UserRole::Gestor);
        $this->actingAs($user);

        $this->get(route('logistics.yards.index'))
            ->assertOk()
            ->assertSee('Pátios e filiais');

        $yard = Yard::create([
            'nome' => 'Pátio Teste',
            'cidade' => 'Contagem',
            'ativo' => true,
            'principal' => true,
        ]);

        $this->assertTrue($yard->fresh()->principal);

        $asset = $this->asset('PAT-YARD-1', AssetStatus::Disponivel);

        Livewire::test(AssetShow::class, ['asset' => $asset])
            ->set('ficha_yard_id', (string) $yard->id)
            ->call('saveFicha')
            ->assertHasNoErrors();

        $this->assertSame($yard->id, $asset->fresh()->yard_id);
    }

    public function test_rental_delivery_schedule_and_daily_list(): void
    {
        $user = $this->user(UserRole::Comercial);
        $this->actingAs($user);

        $yard = Yard::create([
            'nome' => 'Pátio BH',
            'cidade' => 'Belo Horizonte',
            'ativo' => true,
            'principal' => true,
        ]);

        $asset = $this->asset('PAT-LOG-1', AssetStatus::Disponivel);
        $asset->update(['yard_id' => $yard->id]);

        $customer = $this->customer();
        $rental = Rental::create([
            'codigo' => 'LOC-LOG-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'local_obra' => 'Obra RMBH — Contagem',
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(7),
        ]);

        $deliveryDate = now()->addDay()->toDateString();

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->set('ficha_entrega_agendada_em', $deliveryDate)
            ->call('saveRentalField', 'ficha_entrega_agendada_em')
            ->set('ficha_entrega_turno', LogisticsShift::Manha->value)
            ->call('saveRentalField', 'ficha_entrega_turno')
            ->set('ficha_entrega_observacoes', 'Portão lateral')
            ->call('saveRentalField', 'ficha_entrega_observacoes')
            ->assertHasNoErrors();

        $rental->refresh();
        $this->assertSame($deliveryDate, $rental->entrega_agendada_em?->toDateString());
        $this->assertSame(LogisticsShift::Manha->value, $rental->entrega_turno);
        $this->assertSame('Portão lateral', $rental->entrega_observacoes);

        $query = app(LogisticsDailyQuery::class);
        $deliveries = $query->scheduledDeliveries(now()->addDay());
        $this->assertCount(1, $deliveries);
        $this->assertSame($rental->id, $deliveries->first()->id);

        Livewire::test(LogisticsDailyIndex::class)
            ->set('selectedDate', $deliveryDate)
            ->assertSee('LOC-LOG-1')
            ->assertSee('Obra RMBH — Contagem')
            ->assertSee('Pátio BH — Belo Horizonte');
    }

    public function test_daily_list_excludes_deliveries_on_other_dates(): void
    {
        $user = $this->user(UserRole::Comercial);
        $this->actingAs($user);

        $asset = $this->asset('PAT-LOG-2', AssetStatus::Disponivel);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-LOG-2',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'entrega_agendada_em' => now()->addDays(3),
            'entrega_turno' => LogisticsShift::Tarde->value,
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(10),
        ]);

        $todayDeliveries = app(LogisticsDailyQuery::class)->scheduledDeliveries(now());
        $this->assertCount(0, $todayDeliveries);

        Livewire::test(LogisticsDailyIndex::class)
            ->set('selectedDate', now()->toDateString())
            ->assertDontSee('LOC-LOG-2');
    }

    public function test_customer_pickup_excluded_from_company_deliveries(): void
    {
        $user = $this->user(UserRole::Comercial);
        $this->actingAs($user);

        $yard = Yard::create([
            'nome' => 'Pátio BH',
            'cidade' => 'Belo Horizonte',
            'ativo' => true,
        ]);

        $asset = $this->asset('PAT-LOG-3', AssetStatus::Disponivel);
        $asset->update(['yard_id' => $yard->id]);
        $customer = $this->customer();
        $date = now()->addDay()->toDateString();

        $rental = Rental::create([
            'codigo' => 'LOC-LOG-3',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'entrega_modalidade' => LogisticsDeliveryMode::ClienteRetira->value,
            'entrega_agendada_em' => $date,
            'entrega_turno' => LogisticsShift::Tarde->value,
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(7),
        ]);

        $query = app(LogisticsDailyQuery::class);
        $day = now()->addDay();

        $this->assertCount(0, $query->scheduledDeliveries($day));
        $this->assertCount(1, $query->customerPickupsAtYard($day));

        Livewire::test(LogisticsDailyIndex::class)
            ->set('selectedDate', $date)
            ->assertSee('LOC-LOG-3')
            ->assertSee('Cliente retira no pátio');
    }

    public function test_customer_return_excluded_from_company_pickups(): void
    {
        $user = $this->user(UserRole::Comercial);
        $this->actingAs($user);

        $asset = $this->asset('PAT-LOG-4', AssetStatus::Disponivel);
        $customer = $this->customer();
        $date = now()->addDays(2)->toDateString();

        Rental::create([
            'codigo' => 'LOC-LOG-4',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'retirada_modalidade' => LogisticsReturnMode::ClienteDevolve->value,
            'retirada_agendada_em' => $date,
            'retirada_turno' => LogisticsShift::Manha->value,
            'reserved_at' => now()->subDays(5),
            'checkout_at' => now()->subDays(5),
            'expected_return_at' => now()->addDays(2),
        ]);

        $query = app(LogisticsDailyQuery::class);
        $day = now()->addDays(2);

        $this->assertCount(0, $query->scheduledPickups($day));
        $this->assertCount(1, $query->customerReturnsAtYard($day));
    }

    public function test_manifest_generation_assigns_fleet_stops_and_proof(): void
    {
        $user = $this->user(UserRole::Operacao);
        $this->actingAs($user);

        $driver = DeliveryDriver::create(['nome' => 'João Motorista', 'ativo' => true]);
        $vehicle = DeliveryVehicle::create(['placa' => 'ABC1D23', 'descricao' => 'Fiorino', 'ativo' => true]);

        $asset = $this->asset('PAT-ROM-1', AssetStatus::Reservado);
        $customer = $this->customer();
        $date = now()->addDay();

        $rental = Rental::create([
            'codigo' => 'LOC-ROM-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'entrega_agendada_em' => $date->toDateString(),
            'entrega_turno' => LogisticsShift::Manha->value,
            'local_obra' => 'Obra Centro',
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(7),
        ]);

        $manifest = app(DeliveryManifestService::class)->generateForDate($date);
        $this->assertSame(1, $manifest->stops()->count());

        $manifest = app(DeliveryManifestService::class)->assignResources($manifest, $driver, $vehicle);
        $manifest = app(DeliveryManifestService::class)->startRoute($manifest);

        $stop = $manifest->stops()->first();
        app(DeliveryManifestService::class)->recordProof(
            $stop,
            'Maria Recebedora',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );

        $manifest->refresh();
        $this->assertSame(DeliveryManifestStatus::Concluido->value, $manifest->status);
        $this->assertDatabaseHas('delivery_proofs', [
            'delivery_manifest_stop_id' => $stop->id,
            'receptor_nome' => 'Maria Recebedora',
        ]);

        Livewire::test(DeliveryManifestShow::class, ['manifest' => $manifest->fresh()])
            ->assertSee('ROM-')
            ->assertSee('João Motorista')
            ->assertSee('ABC1D23')
            ->assertSee('Maria Recebedora');
    }

    public function test_daily_list_links_to_manifest_generation(): void
    {
        $user = $this->user(UserRole::Operacao);
        $this->actingAs($user);

        $asset = $this->asset('PAT-ROM-2', AssetStatus::Reservado);
        $customer = $this->customer();
        $date = now()->addDays(2)->toDateString();

        Rental::create([
            'codigo' => 'LOC-ROM-2',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'entrega_agendada_em' => $date,
            'entrega_turno' => LogisticsShift::Tarde->value,
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(5),
        ]);

        Livewire::test(LogisticsDailyIndex::class)
            ->set('selectedDate', $date)
            ->assertSee('Gerar romaneio do dia')
            ->call('openManifest')
            ->assertRedirect(route('logistics.manifest.show', DeliveryManifest::query()->whereDate('data', $date)->first()));
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
            'nome' => 'Cliente Logística',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Logística',
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

        return app(AssetStatusService::class)->createWithInitialStatus($asset, $status);
    }
}
