<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSmokeContext;
use Tests\TestCase;

class SmokeRoutesTest extends TestCase
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
     * @return array<string, array{string}>
     */
    public static function publicAndIndexRoutesProvider(): array
    {
        return [
            'health' => ['health'],
            'dashboard' => ['dashboard'],
            'global search' => ['search.results'],
            'profile' => ['profile'],
            'categories' => ['fleet.categories.index'],
            'models' => ['fleet.models.index'],
            'pricing' => ['fleet.pricing.index'],
            'assets' => ['assets.index'],
            'customers' => ['customers.index'],
            'people' => ['people.index'],
            'companies' => ['companies.index'],
            'rentals' => ['rentals.index'],
            'quotes' => ['quotes.index'],
            'crm pipeline' => ['crm.pipeline'],
            'crm inactive' => ['crm.inactive'],
            'crm messages' => ['crm.messages'],
            'logistics daily' => ['logistics.daily'],
            'works map' => ['logistics.works-map'],
            'delivery fleet' => ['logistics.fleet.index'],
            'yards' => ['logistics.yards.index'],
            'commercial report' => ['reports.commercial'],
            'financial analysis' => ['reports.financial-analysis'],
            'fleet report' => ['reports.fleet'],
            'maintenance cost' => ['reports.maintenance-cost'],
            'receivables' => ['finance.receivables'],
            'payables' => ['finance.payables'],
            'billing queue' => ['finance.billing-queue'],
            'delinquency' => ['finance.delinquency'],
            'cashflow' => ['finance.cashflow'],
            'fiscal' => ['finance.fiscal'],
            'maintenance' => ['maintenance.index'],
            'parts' => ['maintenance.parts.index'],
            'purchase orders' => ['maintenance.purchase-orders.index'],
            'preventive' => ['maintenance.preventive.index'],
            'admin users' => ['admin.users.index'],
            'admin companies' => ['admin.companies.index'],
            'audit' => ['admin.audit.index'],
            'agent logs' => ['admin.agent-logs.index'],
            'agent metrics' => ['admin.agent-metrics.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicAndIndexRoutesProvider')]
    public function test_routes_respond_successfully(string $routeName): void
    {
        $url = $routeName === 'search.results'
            ? route($routeName, ['q' => 'smoke'])
            : route($routeName);

        $response = $routeName === 'health'
            ? $this->get($url)
            : $this->actingAs($this->admin)->get($url);

        $response->assertOk();
    }

    public function test_detail_routes_respond_successfully(): void
    {
        $fixtures = $this->createMinimalOperationalFixtures();

        $routes = [
            route('customers.show', $fixtures['customer']),
            route('people.show', $fixtures['person']),
            route('assets.show', $fixtures['asset']),
            route('rentals.show', $fixtures['rental']),
            route('fleet.categories.show', $fixtures['category']),
        ];

        foreach ($routes as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect();
    }
}
