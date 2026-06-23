<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;

use App\Enums\AssetStatus;
use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Livewire\Admin\AuditIndex;
use App\Livewire\Admin\OperatingCompanyIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Crm\CommercialPipelineIndex;
use App\Livewire\Crm\InactiveCustomersIndex;
use App\Livewire\Crm\OutboundMessagesIndex;
use App\Livewire\Customer\CustomerIndex;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Finance\BillingQueueIndex;
use App\Livewire\Finance\CashFlowIndex;
use App\Livewire\Finance\DelinquencyReportIndex;
use App\Livewire\Finance\PayableIndex;
use App\Livewire\Finance\ReceivableIndex;
use App\Livewire\Fiscal\FiscalDocumentIndex;
use App\Livewire\Fleet\AssetIndex;
use App\Livewire\Fleet\CategoryIndex;
use App\Livewire\Fleet\ModelIndex;
use App\Livewire\Logistics\ActiveWorksMapIndex;
use App\Livewire\Logistics\LogisticsDailyIndex;
use App\Livewire\Logistics\LogisticsFleetIndex;
use App\Livewire\Logistics\YardIndex;
use App\Livewire\Maintenance\MaintenanceOrderIndex;
use App\Livewire\Maintenance\PartCatalogIndex;
use App\Livewire\Maintenance\PartPurchaseOrderIndex;
use App\Livewire\Maintenance\PreventiveRuleIndex;
use App\Livewire\Person\CompanyIndex;
use App\Livewire\Person\PersonIndex;
use App\Livewire\Pricing\PricingIndex;
use App\Livewire\Rental\QuoteIndex;
use App\Livewire\Rental\RentalIndex;
use App\Livewire\Reports\CommercialReportIndex;
use App\Livewire\Reports\FinancialAnalysisIndex;
use App\Livewire\Reports\FleetAnalyticsIndex;
use App\Livewire\Reports\MaintenanceCostReportIndex;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSmokeContext;
use Tests\TestCase;


#[Group('livewire')]
class LivewirePagesSmokeTest extends TestCase
{
    use CreatesSmokeContext;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->adminUser();
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function livewireIndexComponentsProvider(): array
    {
        return [
            'dashboard' => [DashboardIndex::class],
            'commercial report' => [CommercialReportIndex::class],
            'financial analysis' => [FinancialAnalysisIndex::class],
            'fleet analytics' => [FleetAnalyticsIndex::class],
            'maintenance cost report' => [MaintenanceCostReportIndex::class],
            'rentals' => [RentalIndex::class],
            'quotes' => [QuoteIndex::class],
            'crm pipeline' => [CommercialPipelineIndex::class],
            'crm inactive' => [InactiveCustomersIndex::class],
            'crm messages' => [OutboundMessagesIndex::class],
            'customers' => [CustomerIndex::class],
            'people' => [PersonIndex::class],
            'companies' => [CompanyIndex::class],
            'pricing' => [PricingIndex::class],
            'logistics daily' => [LogisticsDailyIndex::class],
            'works map' => [ActiveWorksMapIndex::class],
            'delivery fleet' => [LogisticsFleetIndex::class],
            'yards' => [YardIndex::class],
            'assets' => [AssetIndex::class],
            'maintenance orders' => [MaintenanceOrderIndex::class],
            'parts catalog' => [PartCatalogIndex::class],
            'purchase orders' => [PartPurchaseOrderIndex::class],
            'preventive rules' => [PreventiveRuleIndex::class],
            'categories' => [CategoryIndex::class],
            'models' => [ModelIndex::class],
            'receivables' => [ReceivableIndex::class],
            'payables' => [PayableIndex::class],
            'billing queue' => [BillingQueueIndex::class],
            'delinquency' => [DelinquencyReportIndex::class],
            'cashflow' => [CashFlowIndex::class],
            'fiscal' => [FiscalDocumentIndex::class],
            'users' => [UserIndex::class],
            'operating companies' => [OperatingCompanyIndex::class],
            'audit' => [AuditIndex::class],
            // agent logs/metrics: layout-heavy; covered by AgentAdminPagesTest and AgentCopilotTest.
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('livewireIndexComponentsProvider')]
    public function test_livewire_index_pages_render_for_admin(string $componentClass): void
    {
        Livewire::actingAs($this->admin)
            ->test($componentClass)
            ->assertOk();
    }

    public function test_archive_blocks_company_with_linked_people(): void
    {
        $company = Company::create([
            'nome' => 'Empresa com pessoas',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        Person::create([
            'nome' => 'Contato vinculado',
            'company_id' => $company->id,
            'ativo' => true,
        ]);

        $this->expectException(\App\Exceptions\ArchiveBlockedException::class);

        app(ArchiveService::class)->archive($company);
    }

    public function test_archive_blocks_asset_that_is_rented(): void
    {
        $fixtures = $this->createMinimalOperationalFixtures();
        $asset = $fixtures['asset'];
        $asset->update(['status' => AssetStatus::Locado->value]);

        $this->expectException(\App\Exceptions\ArchiveBlockedException::class);

        app(ArchiveService::class)->archive($asset->fresh());
    }

    public function test_company_archive_shows_error_in_livewire(): void
    {
        $company = Company::create([
            'nome' => 'Empresa bloqueada',
            'tipo' => CompanyType::Externa->value,
            'ativo' => true,
        ]);

        Person::create([
            'nome' => 'Funcionário',
            'company_id' => $company->id,
            'ativo' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CompanyIndex::class)
            ->call('archiveRecord', $company->id, Company::class)
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('companies', ['id' => $company->id]);
    }
}
