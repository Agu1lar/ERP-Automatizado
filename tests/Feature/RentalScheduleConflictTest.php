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
use App\Support\RentalScheduleConflictService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_future_reservation_allowed_after_current_rental_period(): void
    {
        $user = $this->comercialUser();
        $asset = $this->asset('PAT-FUT-1', AssetStatus::Locado);
        $customer = $this->customer('Cliente Futuro', '39053344705');

        Rental::create([
            'codigo' => 'LOC-ATUAL',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(5),
            'checkout_at' => now()->subDays(4),
            'expected_return_at' => now()->addDays(5),
        ]);

        $this->actingAs($user);

        $future = app(RentalService::class)->reserve(
            $asset->fresh(),
            $customer,
            now()->addDays(10),
            null,
            null,
            null,
            null,
            now()->addDays(6),
        );

        $this->assertSame(RentalStatus::Reservado->value, $future->status);
        $this->assertTrue($future->isFutureReservation());
        $this->assertSame(AssetStatus::Locado->value, $asset->fresh()->status);
    }

    public function test_overlapping_future_reservation_is_rejected(): void
    {
        $user = $this->comercialUser();
        $asset = $this->asset('PAT-FUT-2', AssetStatus::Locado);
        $customer = $this->customer('Cliente Conflito', '15350946056');

        Rental::create([
            'codigo' => 'LOC-OCUPA',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now()->subDays(2),
            'checkout_at' => now()->subDay(),
            'expected_return_at' => now()->addDays(10),
        ]);

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflito de agenda');

        app(RentalService::class)->reserve(
            $asset->fresh(),
            $customer,
            now()->addDays(8),
            null,
            null,
            null,
            null,
            now()->addDays(5),
        );
    }

    public function test_conflict_service_detects_overlap(): void
    {
        $asset = $this->asset('PAT-DET-1');
        $customer = $this->customer('Cliente Det', '52998224725');

        Rental::create([
            'codigo' => 'LOC-A',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Reservado->value,
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(7),
        ]);

        $service = app(RentalScheduleConflictService::class);
        $result = $service->analyze(
            $asset->id,
            now()->addDays(3),
            now()->addDays(10),
        );

        $this->assertTrue($result->hasConflict);
        $this->assertCount(1, $result->overlapping);
    }

    public function test_unique_index_blocks_duplicate_occupied_rental(): void
    {
        $asset = $this->asset('PAT-IDX-1', AssetStatus::Locado);
        $customer = $this->customer('Cliente Idx', '11144477735');

        Rental::create([
            'codigo' => 'LOC-IDX-1',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
            'checkout_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Rental::create([
            'codigo' => 'LOC-IDX-2',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
            'checkout_at' => now(),
        ]);
    }

    private function comercialUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        return $user;
    }

    private function customer(string $nome, string $cpf): Customer
    {
        return Customer::create([
            'nome' => $nome,
            'cpf_cnpj' => $cpf,
            'ativo' => true,
        ]);
    }

    private function asset(string $code, AssetStatus $status = AssetStatus::Disponivel): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Agenda',
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
