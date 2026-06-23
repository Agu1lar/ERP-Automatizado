<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Person\Company;
use App\Models\User;
use App\Services\ArchiveService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_archive_service_soft_deletes_and_marks_inactive(): void
    {
        $company = Company::create([
            'nome' => 'Empresa Teste',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        app(ArchiveService::class)->archive($company);

        $company->refresh();

        $this->assertTrue($company->trashed());
        $this->assertFalse($company->ativo);
        $this->assertNull(Company::query()->find($company->id));
        $this->assertNotNull(Company::onlyTrashed()->find($company->id));
    }

    public function test_archive_service_restores_record(): void
    {
        $company = Company::create([
            'nome' => 'Empresa Restaurar',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $service = app(ArchiveService::class);
        $service->archive($company);
        $service->restore($company->fresh());

        $company->refresh();

        $this->assertFalse($company->trashed());
        $this->assertTrue($company->ativo);
    }

    public function test_purge_removes_records_archived_beyond_retention(): void
    {
        $company = Company::create([
            'nome' => 'Empresa Expirada',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $company->delete();
        Company::onlyTrashed()->whereKey($company->id)->update([
            'deleted_at' => now()->subDays(31),
        ]);

        $result = app(ArchiveService::class)->purgeExpired(now()->subDays(30));

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_authorized_admin_can_archive_company(): void
    {
        $admin = $this->adminUser();
        $company = Company::create([
            'nome' => 'Empresa Autorizada',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        $this->actingAs($admin);
        $this->assertTrue($admin->can('delete', $company));

        app(ArchiveService::class)->archive($company);

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_delivery_driver_can_be_archived(): void
    {
        $admin = $this->adminUser();
        $driver = DeliveryDriver::create([
            'nome' => 'Motorista Antigo',
            'ativo' => true,
        ]);

        $this->actingAs($admin);
        $this->assertTrue($admin->can('delete', $driver));

        app(ArchiveService::class)->archive($driver);

        $this->assertSoftDeleted('delivery_drivers', ['id' => $driver->id]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }
}
