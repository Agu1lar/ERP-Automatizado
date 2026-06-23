<?php

namespace Tests\Concerns;

use App\Enums\AssetStatus;
use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\Yard;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use App\Services\AssetStatusService;
use App\Support\ActiveOperatingCompany;
use Database\Seeders\OperatingCompanySeeder;
use Database\Seeders\RolePermissionSeeder;

trait CreatesSmokeContext
{
    protected function seedRolesAndCompanies(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            OperatingCompanySeeder::class,
        ]);
    }

    protected function adminUser(): User
    {
        $this->seedRolesAndCompanies();

        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        $companyId = OperatingCompany::query()->orderBy('id')->value('id');
        ActiveOperatingCompany::set((int) $companyId);

        return $user;
    }

    /**
     * @return array{
     *     category: EquipmentCategory,
     *     model: EquipmentModel,
     *     asset: Asset,
     *     customer: Customer,
     *     rental: Rental,
     *     yard: Yard,
     *     crmCompany: Company,
     *     person: Person,
     *     driver: DeliveryDriver,
     * }
     */
    protected function createMinimalOperationalFixtures(): array
    {
        $operatingCompanyId = ActiveOperatingCompany::id()
            ?? OperatingCompany::query()->orderBy('id')->value('id');

        $category = EquipmentCategory::create([
            'operating_company_id' => $operatingCompanyId,
            'nome' => 'Smoke Categoria',
            'tipo_linha' => 'linha_leve',
            'ativo' => true,
        ]);

        $model = EquipmentModel::create([
            'operating_company_id' => $operatingCompanyId,
            'equipment_category_id' => $category->id,
            'marca' => 'Smoke',
            'modelo' => 'SM-01',
            'ativo' => true,
        ]);

        $asset = new Asset([
            'operating_company_id' => $operatingCompanyId,
            'equipment_model_id' => $model->id,
            'codigo_patrimonio' => 'SMOKE-001',
            'localizacao' => 'Pátio teste',
        ]);

        app(AssetStatusService::class)->createWithInitialStatus($asset, AssetStatus::Disponivel);

        $customer = Customer::create([
            'nome' => 'Cliente Smoke Test',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        $rental = Rental::create([
            'codigo' => 'LOC-SMOKE-001',
            'asset_id' => $asset->id,
            'customer_id' => $customer->id,
            'status' => 'reservado',
            'reserved_at' => now(),
            'expected_return_at' => now()->addDays(7),
        ]);

        $yard = Yard::create([
            'operating_company_id' => $operatingCompanyId,
            'nome' => 'Pátio Smoke',
            'cidade' => 'Belo Horizonte',
            'ativo' => true,
        ]);

        $crmCompany = Company::create([
            'nome' => 'Empresa CRM Smoke',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $person = Person::create([
            'nome' => 'Pessoa Smoke',
            'cpf' => '52998224725',
            'ativo' => true,
        ]);

        $driver = DeliveryDriver::create([
            'operating_company_id' => $operatingCompanyId,
            'nome' => 'Motorista Smoke',
            'ativo' => true,
        ]);

        return compact('category', 'model', 'asset', 'customer', 'rental', 'yard', 'crmCompany', 'person', 'driver');
    }
}
