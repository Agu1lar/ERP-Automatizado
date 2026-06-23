<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgentMetricsIndex;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use Tests\Concerns\CreatesSmokeContext;
use Tests\TestCase;

#[RunClassInSeparateProcess]
class AgentAdminPagesTest extends TestCase
{
    use CreatesSmokeContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
    }

    public function test_agent_metrics_page_renders(): void
    {
        Livewire::actingAs($this->adminUser())
            ->test(AgentMetricsIndex::class)
            ->assertOk()
            ->assertSee('métricas e custo LLM');
    }
}
