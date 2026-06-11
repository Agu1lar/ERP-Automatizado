<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Pricing\PricingIndex;
use App\Livewire\Rental\RentalShow;
use Tests\TestCase;

class Phase10PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_category_price_applies_to_all_assets_in_category(): void
    {
        $category = EquipmentCategory::create([
            'nome' => 'Betoneiras',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $modelA = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Menegotti',
            'modelo' => '160',
            'ativo' => true,
        ]);

        $modelB = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'CSM',
            'modelo' => '400',
            'ativo' => true,
        ]);

        EquipmentPricing::create([
            'equipment_category_id' => $category->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 85,
            'ativo' => true,
        ]);

        $assetA = $this->asset($modelA, 'PAT-BET-01', AssetStatus::Disponivel);
        $assetB = $this->asset($modelB, 'PAT-BET-02', AssetStatus::Disponivel);
        $service = app(RentalPricingService::class);

        $this->assertSame(85.0, $service->resolveUnitPrice($assetA, RentalPricingPeriod::Diaria));
        $this->assertSame(85.0, $service->resolveUnitPrice($assetB, RentalPricingPeriod::Diaria));
    }

    public function test_category_grid_save_updates_all_periods(): void
    {
        $gestor = $this->user(UserRole::Gestor);
        $category = EquipmentCategory::create([
            'nome' => 'Betoneiras',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $this->actingAs($gestor);

        Livewire::test(PricingIndex::class)
            ->set('categoryGrid.'.$category->id.'.diaria', '90')
            ->set('categoryGrid.'.$category->id.'.semanal', '480')
            ->set('categoryGrid.'.$category->id.'.mensal', '1500')
            ->call('saveCategoryRow', $category->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('equipment_pricings', [
            'equipment_category_id' => $category->id,
            'equipment_model_id' => null,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => '90.00',
        ]);

        $this->assertDatabaseHas('equipment_pricings', [
            'equipment_category_id' => $category->id,
            'periodo' => RentalPricingPeriod::Mensal->value,
            'valor' => '1500.00',
        ]);
    }

    public function test_new_category_appears_in_category_grid(): void
    {
        $gestor = $this->user(UserRole::Gestor);
        $category = EquipmentCategory::create([
            'nome' => 'Compactadores',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $this->actingAs($gestor);

        Livewire::test(PricingIndex::class)
            ->assertSet('categoryGrid.'.$category->id.'.diaria', '')
            ->set('categoryGrid.'.$category->id.'.diaria', '120')
            ->call('saveCategoryRow', $category->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('equipment_pricings', [
            'equipment_category_id' => $category->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => '120.00',
        ]);
    }

    public function test_model_price_takes_priority_over_category(): void
    {
        $model = $this->model('Marteletes');
        $asset = $this->asset($model, 'PAT-PRC', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_category_id' => $model->equipment_category_id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 80,
            'ativo' => true,
        ]);

        $service = app(RentalPricingService::class);
        $unitPrice = $service->resolveUnitPrice($asset, RentalPricingPeriod::Diaria);

        $this->assertSame(80.0, $unitPrice);
    }

    public function test_reserve_applies_automatic_pricing(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Betoneiras');
        $asset = $this->asset($model, 'PAT-RES', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 50,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve(
            $asset,
            $customer,
            now()->addDays(4),
        );

        $rental->refresh();

        $this->assertSame(RentalPricingPeriod::Diaria->value, $rental->pricing_period);
        $this->assertSame(5, $rental->billed_days);
        $this->assertSame('250.00', $rental->valor_calculado);
        $this->assertSame('250.00', $rental->valor_faturamento);
    }

    public function test_checkout_recalculates_from_checkout_date(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Geradores');
        $asset = $this->asset($model, 'PAT-CHK', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $rental->refresh();

        $this->assertSame(RentalStatus::Locado->value, $rental->status);
        $this->assertNotNull($rental->valor_calculado);
        $this->assertSame(3, $rental->billed_days);
        $this->assertSame('300.00', $rental->valor_calculado);
    }

    public function test_extend_updates_return_date_and_recalculates(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Andaimes');
        $asset = $this->asset($model, 'PAT-EXT', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 40,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(2));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        $rental = app(RentalService::class)->extend($rental, now()->addDays(6));

        $rental->refresh();

        $this->assertSame(now()->addDays(6)->toDateString(), $rental->expected_return_at->toDateString());
        $this->assertSame(7, $rental->billed_days);
        $this->assertSame('280.00', $rental->valor_calculado);
    }

    public function test_suggest_best_period_picks_cheaper_weekly(): void
    {
        $model = $this->model('Compressores');
        $asset = $this->asset($model, 'PAT-BEST', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 100,
            'ativo' => true,
        ]);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Semanal->value,
            'valor' => 500,
            'ativo' => true,
        ]);

        $period = app(RentalPricingService::class)->suggestBestPeriod($asset, 14);

        $this->assertSame(RentalPricingPeriod::Semanal, $period);
    }

    public function test_pricing_index_requires_permission(): void
    {
        $gestor = $this->user(UserRole::Gestor);

        $this->actingAs($gestor)
            ->get(route('fleet.pricing.index'))
            ->assertOk();

        Livewire::actingAs($gestor)
            ->test(PricingIndex::class)
            ->assertOk();
    }

    public function test_rental_show_extend_modal(): void
    {
        $user = $this->user(UserRole::Comercial);
        $customer = $this->customer();
        $model = $this->model('Outros');
        $asset = $this->asset($model, 'PAT-UI', AssetStatus::Disponivel);

        EquipmentPricing::create([
            'equipment_model_id' => $model->id,
            'periodo' => RentalPricingPeriod::Diaria->value,
            'valor' => 30,
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDays(3));
        $rental = app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
        );

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->call('openExtendModal')
            ->assertSet('showExtendModal', true)
            ->set('extend_expected_return_at', now()->addDays(10)->toDateString())
            ->call('extendRental')
            ->assertSet('showExtendModal', false);

        $rental->refresh();
        $this->assertSame(now()->addDays(10)->toDateString(), $rental->expected_return_at->toDateString());
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
            'nome' => 'Cliente Preço',
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
