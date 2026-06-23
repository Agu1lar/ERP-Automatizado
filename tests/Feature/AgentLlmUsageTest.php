<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('agent')]
class AgentLlmUsageTest extends TestCase
{
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

    public function test_permission_denied_command_is_logged_for_metrics(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->givePermissionTo('agent.api');

        Sanctum::actingAs($user);

        $this->postJson('/api/agent/commands/agent.metrics', ['input' => []])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error_code', 'forbidden');

        $log = AgentCommandLog::query()->where('command', 'agent.metrics')->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->ok);
        $this->assertSame('forbidden', $log->result['error_code'] ?? null);
    }

    public function test_agent_metrics_command_returns_summary(): void
    {
        Sanctum::actingAs($this->agentUser());

        $this->postJson('/api/agent/commands/agent.metrics', ['input' => []])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.entity', 'agent_metrics')
            ->assertJsonStructure(['data' => ['summary' => ['llm', 'commands']]]);
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
