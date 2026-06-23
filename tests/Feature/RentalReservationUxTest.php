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
use App\Support\FichaCompleteness;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalReservationUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_asset_without_horimetro_category_has_no_horimetro_warnings(): void
    {
        $asset = $this->createAsset(usaHorimetro: false);

        $warnings = FichaCompleteness::assetWarnings($asset);

        $this->assertFalse(FichaCompleteness::hasFieldWarning($warnings, 'horimetro'));
    }

    public function test_reserve_with_agreed_value_sets_valor_faturamento(): void
    {
        $this->actingAs($this->comercialUser());

        $asset = $this->createAsset();
        $customer = $this->customer();

        $rental = app(RentalService::class)->reserve(
            $asset,
            $customer,
            now()->addDays(5),
            null,
            null,
            null,
            null,
            null,
            450.50,
        );

        $this->assertSame('450.50', (string) $rental->fresh()->valor_faturamento);
    }

    public function test_advancing_scheduled_start_allows_checkout(): void
    {
        Carbon::setTestNow('2026-06-22 10:00:00');

        $this->actingAs($this->comercialUser());

        $asset = $this->createAsset();
        $customer = $this->customer();
        $service = app(RentalService::class);

        $rental = $service->reserve(
            $asset,
            $customer,
            now()->addDays(10),
            null,
            null,
            null,
            null,
            now()->addDays(5),
        );

        $this->assertTrue($rental->isFutureReservation());

        try {
            $service->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
            $this->fail('Checkout deveria falhar antes do início previsto.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('início', mb_strtolower($e->getMessage()));
        }

        $rental = $service->updateScheduledStart($rental, now());
        $this->assertFalse($rental->isFutureReservation());
        $this->assertSame(AssetStatus::Reservado->value, $asset->fresh()->status);

        $rental = $service->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $this->assertSame(RentalStatus::Locado->value, $rental->status);

        Carbon::setTestNow();
    }

    private function comercialUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'nome' => 'Cliente UX',
            'cpf_cnpj' => '39053344705',
            'telefone' => '31999999999',
            'endereco' => 'Rua Teste',
            'contato' => 'João',
            'ativo' => true,
        ]);
    }

    private function createAsset(bool $usaHorimetro = true): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Categoria UX',
            'tipo_linha' => 'linha_leve',
            'usa_horimetro' => $usaHorimetro,
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => 'PAT-UX-'.uniqid(),
            'equipment_model_id' => $model->id,
            'localizacao' => 'Pátio',
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
