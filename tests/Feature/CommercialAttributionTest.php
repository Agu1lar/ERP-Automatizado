<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Livewire\Rental\RentalShow;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\CommercialReportService;
use App\Services\RentalService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reserve_sets_commercial_user_automatically(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);
        $asset = $this->createAsset();
        $customer = $this->createCustomer($comercial);

        $this->actingAs($comercial);

        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDay());

        $this->assertSame($comercial->id, $rental->commercial_user_id);
        $this->assertSame($comercial->id, $rental->reserved_by);
    }

    public function test_customer_creation_stores_created_by(): void
    {
        $comercial = $this->userWithRole(UserRole::Comercial);
        $this->actingAs($comercial);

        $customer = Customer::create([
            'nome' => 'Cliente Atribuição',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
            'created_by' => $comercial->id,
        ]);

        $this->assertSame($comercial->id, $customer->created_by);
    }

    public function test_commercial_report_groups_revenue_by_user(): void
    {
        $vendedorA = $this->userWithRole(UserRole::Comercial);
        $vendedorB = $this->userWithRole(UserRole::Comercial);
        $customer = $this->createCustomer($vendedorA);

        $this->actingAs($vendedorA);
        $rentalA = $this->createCompletedRental($this->createAsset('PAT-A1'), $customer, 500.00);

        $this->actingAs($vendedorB);
        $rentalB = $this->createCompletedRental($this->createAsset('PAT-B1'), $customer, 300.00);

        $this->assertSame($vendedorA->id, $rentalA->commercial_user_id);
        $this->assertSame($vendedorB->id, $rentalB->commercial_user_id);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $rows = app(CommercialReportService::class)->revenueByCommercialUser($from, $to);

        $this->assertCount(2, $rows);
        $this->assertSame(500.00, $rows->firstWhere('grupo_id', $vendedorA->id)->faturamento_total);
        $this->assertSame(300.00, $rows->firstWhere('grupo_id', $vendedorB->id)->faturamento_total);
    }

    public function test_gestor_can_transfer_commercial_user_after_completion(): void
    {
        $vendedor = $this->userWithRole(UserRole::Comercial);
        $gestor = $this->userWithRole(UserRole::Gestor);
        $outro = $this->userWithRole(UserRole::Comercial);
        $customer = $this->createCustomer($vendedor);

        $this->actingAs($vendedor);
        $rental = $this->createCompletedRental($this->createAsset('PAT-T1'), $customer, 750.00);

        $this->actingAs($gestor);

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->assertSee($vendedor->name)
            ->call('openTransferCommercialModal')
            ->set('transfer_commercial_user_id', (string) $outro->id)
            ->call('transferCommercialUser')
            ->assertHasNoErrors();

        $this->assertSame($outro->id, $rental->fresh()->commercial_user_id);
    }

    public function test_comercial_cannot_transfer_commercial_user(): void
    {
        $vendedor = $this->userWithRole(UserRole::Comercial);
        $outro = $this->userWithRole(UserRole::Comercial);
        $customer = $this->createCustomer($vendedor);

        $this->actingAs($vendedor);
        $rental = $this->createCompletedRental($this->createAsset('PAT-T2'), $customer, 400.00);

        $this->actingAs($vendedor);

        Livewire::test(RentalShow::class, ['rental' => $rental])
            ->assertDontSee('Transferir responsabilidade');

        $this->assertFalse($vendedor->can('transferCommercialUser', $rental));
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function createAsset(string $code = 'PAT-ATTR-1'): Asset
    {
        $category = EquipmentCategory::create([
            'nome' => 'Atribuição',
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

        return app(\App\Services\AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);
    }

    private function createCustomer(User $creator): Customer
    {
        return Customer::create([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function createCompletedRental(Asset $asset, Customer $customer, float $valor): Rental
    {
        $rental = app(RentalService::class)->reserve($asset, $customer, now()->addDay());
        $checked = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), true);
        app(RentalService::class)->checkout($rental, $checked);
        $rental->refresh();

        $checkedRetorno = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), true);
        app(RentalService::class)->registerReturn($rental, $checkedRetorno);
        app(RentalService::class)->completeInspection($rental);

        $rental->update([
            'valor_faturamento' => $valor,
            'completed_at' => now(),
            'status' => RentalStatus::Concluido->value,
        ]);

        return $rental->fresh();
    }
}
