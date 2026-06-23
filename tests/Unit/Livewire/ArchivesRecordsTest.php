<?php

namespace Tests\Unit\Livewire;

use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Livewire\ArchivesRecordsHarness;
use Tests\TestCase;

class ArchivesRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_archive_record_soft_deletes_company_without_page_layout(): void
    {
        $admin = User::factory()->create(['ativo' => true]);
        $admin->assignRole(UserRole::Admin->value);

        $company = Company::create([
            'nome' => 'Empresa Harness',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ArchivesRecordsHarness::class)
            ->call('archiveRecord', $company->id, Company::class)
            ->assertHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }
}
