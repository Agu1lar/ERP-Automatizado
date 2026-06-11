<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OperatingCompanyIndex;
use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use Database\Seeders\OperatingCompanySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperatingCompanyAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OperatingCompanySeeder::class]);
    }

    public function test_admin_can_access_operating_companies_screen(): void
    {
        $admin = User::factory()->create(['ativo' => true]);
        $admin->assignRole(UserRole::Admin->value);

        $this->actingAs($admin)
            ->get(route('admin.companies.index'))
            ->assertOk();
    }

    public function test_comercial_cannot_access_operating_companies_screen(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Comercial->value);

        $this->actingAs($user)
            ->get(route('admin.companies.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_operating_company_data(): void
    {
        $admin = User::factory()->create(['ativo' => true]);
        $admin->assignRole(UserRole::Admin->value);

        $company = OperatingCompany::query()->where('slug', 'acesso')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(OperatingCompanyIndex::class)
            ->call('edit', $company->id)
            ->set('razao_social', 'Acesso Equipamentos Ltda ME')
            ->set('cnpj', '12.345.678/0001-90')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();

        $this->assertSame('Acesso Equipamentos Ltda ME', $company->razao_social);
        $this->assertSame('12.345.678/0001-90', $company->formattedCnpj());
    }
}
