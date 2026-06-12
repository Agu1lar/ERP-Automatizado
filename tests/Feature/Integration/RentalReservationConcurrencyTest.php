<?php

namespace Tests\Feature\Integration;

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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proteção contra dupla reserva do mesmo patrimônio (bug clássico de locadoras).
 *
 * @group integration
 */
class RentalReservationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_second_reserve_fails_when_asset_already_has_active_rental(): void
    {
        $user = $this->comercialUser();
        $asset = $this->asset('PAT-CONC-1', AssetStatus::Disponivel);
        $customerA = $this->customer('Cliente A', '39053344705');
        $customerB = $this->customer('Cliente B', '15350946056');

        $this->actingAs($user);
        $service = app(RentalService::class);

        $service->reserve($asset, $customerA, now()->addDays(2));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Patrimônio já possui locação ativa');

        $service->reserve($asset->fresh(), $customerB, now()->addDays(3));
    }

    public function test_unique_index_blocks_duplicate_occupied_rental_at_database_level(): void
    {
        $asset = $this->asset('PAT-CONC-2', AssetStatus::Locado);
        $customer = $this->customer('Cliente DB', '52998224725');

        Rental::create([
            'codigo' => 'LOC-000001',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
            'checkout_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Rental::create([
            'codigo' => 'LOC-000002',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => RentalStatus::Locado->value,
            'reserved_at' => now(),
            'checkout_at' => now(),
        ]);
    }

    public function test_completed_rental_does_not_block_new_reservation(): void
    {
        $user = $this->comercialUser();
        $asset = $this->asset('PAT-CONC-3', AssetStatus::Disponivel);
        $customer = $this->customer('Cliente Reuso', '39053344705');

        $this->actingAs($user);
        $service = app(RentalService::class);

        $first = $service->reserve($asset, $customer);
        $first = $service->checkout($first, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true));
        $first = $service->registerReturn($first, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true));
        $first = $service->completeInspection($first);

        $second = $service->reserve($asset->fresh(), $customer, now()->addDays(1));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(RentalStatus::Reservado->value, $second->status);
    }

    public function test_reserve_inside_locked_transaction_rechecks_active_rental(): void
    {
        $user = $this->comercialUser();
        $asset = $this->asset('PAT-CONC-4', AssetStatus::Disponivel);
        $customerA = $this->customer('Cliente Lock A', '39053344705');
        $customerB = $this->customer('Cliente Lock B', '15350946056');

        $this->actingAs($user);
        $service = app(RentalService::class);

        $failed = false;

        DB::transaction(function () use ($asset, $customerA, $customerB, $service, &$failed) {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->first();

            $service->reserve($asset->fresh(), $customerA);

            try {
                $service->reserve($asset->fresh(), $customerB);
            } catch (\InvalidArgumentException) {
                $failed = true;
            }
        });

        $this->assertTrue($failed);
        $this->assertSame(1, Rental::query()->where('asset_id', $asset->id)->active()->count());
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

    private function asset(string $code, AssetStatus $status): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Concorrência',
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
