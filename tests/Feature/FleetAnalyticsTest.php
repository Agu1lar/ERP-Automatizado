<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Livewire\Reports\FleetAnalyticsIndex;
use App\Services\FleetAnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


#[Group('livewire')]
class FleetAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_occupancy_counts_committed_rental_days(): void
    {
        $asset = $this->asset('PAT-OC-1', 5000);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-OC-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(9),
            'checkout_at' => now()->subDays(7),
            'expected_return_at' => now()->addDays(3),
        ]);

        $from = now()->subDays(9)->startOfDay();
        $to = now()->endOfDay();

        $row = app(FleetAnalyticsService::class)
            ->occupancy($from, $to, 'asset')
            ->first();

        $this->assertNotNull($row);
        $this->assertGreaterThanOrEqual(8, $row->dias_comprometidos);
        $this->assertGreaterThan(0, $row->taxa_ocupacao);
    }

    public function test_profitability_includes_purchase_value(): void
    {
        $asset = $this->asset('PAT-RENT-1', 2000);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-RENT-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(15),
            'checkout_at' => now()->subDays(14),
            'completed_at' => now()->subDays(2),
            'valor_faturamento' => 800,
        ]);

        $row = app(FleetAnalyticsService::class)
            ->profitabilityByAsset(now()->subMonth(), now())
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(800.0, $row->faturamento);
        $this->assertSame(2000.0, $row->valor_compra);
        $this->assertSame(40.0, $row->retorno_sobre_compra_percent);
    }

    public function test_calendar_marks_rented_and_reserved_days(): void
    {
        $asset = $this->asset('PAT-CAL-1');
        $customer = $this->customer();
        $month = now()->startOfMonth();

        Rental::create([
            'codigo' => 'LOC-CAL-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => $month->copy()->addDays(1),
            'checkout_at' => $month->copy()->addDays(3),
            'expected_return_at' => $month->copy()->addDays(10),
        ]);

        $calendar = app(FleetAnalyticsService::class)->availabilityCalendar($month);
        $assetRow = collect($calendar['assets'])->firstWhere('id', $asset->id);

        $this->assertNotNull($assetRow);
        $this->assertSame('reservado', $assetRow['days'][(string) $month->copy()->addDays(1)->day]);
        $this->assertSame('locado', $assetRow['days'][(string) $month->copy()->addDays(4)->day]);
    }

    public function test_investment_analysis_calculates_payback_and_book_value(): void
    {
        $asset = $this->asset('PAT-INV-1', 12000);
        $asset->update(['data_compra' => now()->subYears(2)]);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-INV-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(20),
            'checkout_at' => now()->subDays(19),
            'completed_at' => now()->subDays(5),
            'valor_faturamento' => 3000,
        ]);

        $row = app(FleetAnalyticsService::class)
            ->investmentAnalysis(now()->subMonth(), now())
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(12000.0, $row->valor_compra);
        $this->assertNotNull($row->valor_contabil);
        $this->assertLessThan(12000.0, $row->valor_contabil);
        $this->assertNotNull($row->payback_meses);
    }

    public function test_divestment_flags_low_performing_asset(): void
    {
        $asset = $this->asset('PAT-DIV-1', 5000);
        $customer = $this->customer();

        Rental::create([
            'codigo' => 'LOC-DIV-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Concluido->value,
            'reserved_at' => now()->subDays(40),
            'checkout_at' => now()->subDays(39),
            'completed_at' => now()->subDays(30),
            'valor_faturamento' => 100,
        ]);

        $rows = app(FleetAnalyticsService::class)->divestmentSuggestions(now()->subMonths(2), now());

        $this->assertTrue($rows->contains(fn ($row) => $row->grupo_id === $asset->id));
    }

    public function test_fleet_analytics_page_loads(): void
    {
        $gestor = $this->user(UserRole::Gestor);
        $this->actingAs($gestor);

        Livewire::test(FleetAnalyticsIndex::class)
            ->assertSee('Indicadores de frota')
            ->assertSee('Taxa média de ocupação')
            ->set('tab', 'rentabilidade')
            ->assertSee('Valor compra')
            ->set('tab', 'investimento')
            ->assertSee('Valor contábil')
            ->set('tab', 'desinvestimento')
            ->assertSee('desinvestimento')
            ->set('tab', 'calendario')
            ->assertSee('Livre');
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
            'nome' => 'Cliente Frota',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);
    }

    private function asset(string $code, ?float $valorCompra = null): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Frota',
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
            'valor_compra' => $valorCompra,
        ]);

        return app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
