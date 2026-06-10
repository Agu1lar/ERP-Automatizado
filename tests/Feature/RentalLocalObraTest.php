<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Services\RentalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalLocalObraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reserve_prefills_local_obra_from_customer_address(): void
    {
        $user = $this->user();
        $asset = $this->asset('PAT-LOC', 'Pátio A');
        $customer = Customer::create([
            'nome' => 'Obra Centro',
            'cpf_cnpj' => '52998224725',
            'endereco' => 'Rua das Obras, 100 — São Paulo',
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve($asset, $customer);

        $this->assertSame('Rua das Obras, 100 — São Paulo', $rental->local_obra);
    }

    public function test_checkout_moves_asset_location_to_local_obra(): void
    {
        $user = $this->user();
        $asset = $this->asset('PAT-MOVE', 'Pátio B');
        $customer = Customer::create([
            'nome' => 'Cliente Obra',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve(
            $asset,
            $customer,
            null,
            null,
            $user,
            'Av. Paulista, 500 — Obra Norte',
        );

        app(RentalService::class)->checkout(
            $rental,
            array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true),
            null,
            $user,
        );

        $asset->refresh();
        $rental->refresh();

        $this->assertSame('Av. Paulista, 500 — Obra Norte', $asset->localizacao);
        $this->assertSame('Pátio B', $rental->localizacao_origem);
        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'origem' => 'Pátio B',
            'destino' => 'Av. Paulista, 500 — Obra Norte',
        ]);
    }

    public function test_complete_inspection_restores_asset_origin_location(): void
    {
        $user = $this->user();
        $asset = $this->asset('PAT-RET', 'Depósito');
        $customer = Customer::create([
            'nome' => 'Cliente Retorno',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        $this->actingAs($user);

        $rental = app(RentalService::class)->reserve(
            $asset,
            $customer,
            null,
            null,
            $user,
            'Obra Sul — Rua 10',
        );

        $service = app(RentalService::class);
        $rental = $service->checkout($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true), null, $user);
        $rental = $service->registerReturn($rental, array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true), null, $user);
        $service->completeInspection($rental, false, null, $user);

        $this->assertSame('Depósito', $asset->fresh()->localizacao);
    }

    private function user(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Operacao->value);

        return $user;
    }

    private function asset(string $code, string $localizacao): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Martelete',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'equipment_category_id' => $category->id,
            'marca' => 'Bosch',
            'modelo' => 'GBH',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'codigo_patrimonio' => $code,
            'equipment_model_id' => $model->id,
            'localizacao' => $localizacao,
        ]);

        return app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }
}
