<?php

namespace Tests\Feature;

use App\Enums\AgentTaskStatus;
use App\Enums\UserRole;
use App\Models\Domain\Agent\AgentTask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentTaskStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_task_stream_returns_sse_headers(): void
    {
        $user = $this->agentUser();
        Sanctum::actingAs($user);

        $task = AgentTask::create([
            'user_id' => $user->id,
            'status' => AgentTaskStatus::Completed->value,
            'title' => 'Teste SSE',
            'total_steps' => 1,
            'current_step' => 1,
            'steps' => [['command' => 'finance.summary', 'params' => []]],
            'finished_at' => now(),
        ]);

        $response = $this->get('/api/agent/tasks/'.$task->id.'/stream');

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('event: task', $response->streamedContent());
        $this->assertStringContainsString('event: close', $response->streamedContent());
        $this->assertStringContainsString('"status":"completed"', $response->streamedContent());
    }

    public function test_manifest_includes_stream_endpoint(): void
    {
        Sanctum::actingAs($this->agentUser());

        $this->getJson('/api/agent/manifest')
            ->assertOk()
            ->assertJsonPath('version', '1.4')
            ->assertJsonPath('task_endpoints.stream', url('/api/agent/tasks/{id}/stream'));
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
