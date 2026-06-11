<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Support\ActiveOperatingCompany;
use Database\Seeders\OperatingCompanySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperatingCompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OperatingCompany $acesso;

    private OperatingCompany $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            OperatingCompanySeeder::class,
        ]);

        $this->user = User::factory()->create(['ativo' => true]);
        $this->user->assignRole(UserRole::Admin->value);

        $this->acesso = OperatingCompany::query()->where('slug', 'acesso')->firstOrFail();
        $this->super = OperatingCompany::query()->where('slug', 'supermaquinas')->firstOrFail();
    }

    public function test_user_can_switch_active_operating_company(): void
    {
        ActiveOperatingCompany::set($this->acesso->id);

        $this->actingAs($this->user)
            ->post(route('operating-company.set'), ['company_id' => $this->super->id])
            ->assertRedirect();

        $this->assertSame($this->super->id, ActiveOperatingCompany::id());
    }

    public function test_assets_are_scoped_to_active_company(): void
    {
        $this->createAssetForCompany($this->acesso, 'AC-SCOPE-1');
        $this->createAssetForCompany($this->super, 'SM-SCOPE-1');

        ActiveOperatingCompany::set($this->acesso->id);
        $this->assertSame(1, Asset::query()->count());
        $this->assertTrue(Asset::query()->where('codigo_patrimonio', 'AC-SCOPE-1')->exists());

        ActiveOperatingCompany::set($this->super->id);
        $this->assertSame(1, Asset::query()->count());
        $this->assertTrue(Asset::query()->where('codigo_patrimonio', 'SM-SCOPE-1')->exists());
    }

    public function test_customers_and_people_remain_shared_across_companies(): void
    {
        $customer = Customer::create([
            'nome' => 'Cliente Compartilhado',
            'cpf_cnpj' => '52998224725',
            'ativo' => true,
        ]);

        ActiveOperatingCompany::set($this->acesso->id);
        $this->assertTrue(Customer::query()->whereKey($customer->id)->exists());

        ActiveOperatingCompany::set($this->super->id);
        $this->assertTrue(Customer::query()->whereKey($customer->id)->exists());

        $person = Person::create([
            'nome' => 'Pessoa Compartilhada',
            'cpf' => '39053344705',
            'ativo' => true,
            'created_by' => $this->user->id,
        ]);

        ActiveOperatingCompany::set($this->acesso->id);
        $this->assertTrue(Person::query()->whereKey($person->id)->exists());

        ActiveOperatingCompany::set($this->super->id);
        $this->assertTrue(Person::query()->whereKey($person->id)->exists());
    }

    public function test_rental_carries_operating_company_for_documents(): void
    {
        ActiveOperatingCompany::set($this->super->id);

        $asset = $this->createAssetForCompany($this->super, 'SM-TEST-01');

        $customer = Customer::create([
            'nome' => 'Cliente PDF',
            'cpf_cnpj' => '11222333000181',
            'ativo' => true,
        ]);

        $rental = Rental::withoutGlobalScope('operating_company')->create([
            'operating_company_id' => $this->super->id,
            'codigo' => 'LOC-TEST-SM',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => 'reservado',
            'reserved_at' => now(),
        ]);

        $rental->load('operatingCompany');
        $header = $rental->operatingCompany->documentHeader();

        $this->assertSame($this->super->id, $rental->operating_company_id);
        $this->assertStringContainsString('Super Máquinas', $header['name']);
        $this->assertSame('98.765.432/0001-10', $header['document']);
    }

    public function test_scoped_tables_have_operating_company_composite_indexes(): void
    {
        $expected = [
            'rentals' => 'rentals_oc_status_idx',
            'receivable_titles' => 'recv_titles_oc_status_idx',
            'rental_billing_queue' => 'billing_queue_oc_status_idx',
            'assets' => 'assets_oc_status_idx',
        ];

        foreach ($expected as $table => $indexName) {
            $names = collect(Schema::getIndexes($table))->map->name->all();
            $this->assertContains($indexName, $names, "Missing index {$indexName} on {$table}");
        }
    }

    private function createAssetForCompany(OperatingCompany $company, string $code): Asset
    {
        $category = EquipmentCategory::withoutGlobalScope('operating_company')->create([
            'operating_company_id' => $company->id,
            'nome' => 'Categoria '.$code,
            'tipo_linha' => 'leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::withoutGlobalScope('operating_company')->create([
            'operating_company_id' => $company->id,
            'equipment_category_id' => $category->id,
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ativo' => true,
        ]);

        ActiveOperatingCompany::set($company->id);

        return Asset::create([
            'operating_company_id' => $company->id,
            'equipment_model_id' => $model->id,
            'codigo_patrimonio' => $code,
            'status' => 'disponivel',
        ]);
    }
}
