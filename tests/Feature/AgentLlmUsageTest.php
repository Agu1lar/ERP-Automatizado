<?php

namespace Tests\Feature;

use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\Domain\Agent\AgentLlmCall;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentLlmUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_llm_success_records_usage_tokens_and_cost(): void
    {
        $this->enableLlm();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Olá!']],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 20,
                    'total_tokens' => 120,
                ],
            ], 200),
        ]);

        $user = $this->agentUser();
        $response = app(AgentChatOrchestrator::class)->handle(
            'Quem te criou?',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertFalse($response->llmDegraded);

        $call = AgentLlmCall::query()->where('call_type', AgentLlmCall::TYPE_CHAT_INTERPRET)->first();
        $this->assertNotNull($call);
        $this->assertTrue($call->success);
        $this->assertSame(120, $call->total_tokens);
        $this->assertGreaterThan(0, (float) $call->estimated_cost_usd);
        $this->assertSame($user->id, $call->user_id);
    }

    public function test_llm_tool_call_success_is_recorded(): void
    {
        $this->enableLlm();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'tool_calls' => [
                                [
                                    'function' => [
                                        'name' => 'finance.summary',
                                        'arguments' => '{}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 30, 'total_tokens' => 530],
            ], 200),
        ]);

        $user = $this->agentUser();
        $response = app(AgentChatOrchestrator::class)->handle(
            'Me dá o resumo financeiro',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertTrue($response->executed);
        $this->assertDatabaseHas('agent_llm_calls', [
            'call_type' => AgentLlmCall::TYPE_CHAT_INTERPRET,
            'success' => true,
            'total_tokens' => 530,
        ]);
    }

    public function test_llm_failure_records_fallback_event(): void
    {
        $this->enableLlm();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'quota exceeded', 'type' => 'insufficient_quota'],
            ], 429),
        ]);

        $user = $this->agentUser();
        $response = app(AgentChatOrchestrator::class)->handle(
            'Resumo financeiro',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertTrue($response->llmDegraded);
        $this->assertDatabaseHas('agent_llm_calls', [
            'call_type' => AgentLlmCall::TYPE_CHAT_INTERPRET,
            'success' => false,
            'failure_reason' => 'quota_exceeded',
        ]);
        $this->assertDatabaseHas('agent_llm_calls', [
            'call_type' => AgentLlmCall::TYPE_HEURISTIC_FALLBACK,
            'used_fallback' => true,
        ]);
    }

    public function test_local_daily_quota_blocks_llm_before_http(): void
    {
        $this->enableLlm();
        config(['agent.llm.daily_token_limit' => 100]);

        Http::fake();

        $user = $this->agentUser();
        AgentLlmCall::create([
            'user_id' => $user->id,
            'operating_company_id' => \App\Support\ActiveOperatingCompany::id(),
            'call_type' => AgentLlmCall::TYPE_CHAT_INTERPRET,
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 80,
            'completion_tokens' => 30,
            'total_tokens' => 110,
            'estimated_cost_usd' => 0.01,
            'success' => true,
            'used_fallback' => false,
            'created_at' => now(),
        ]);

        $response = app(AgentChatOrchestrator::class)->handle(
            'Resumo financeiro',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertTrue($response->llmDegraded);
        Http::assertNothingSent();
        $this->assertDatabaseHas('agent_llm_calls', [
            'failure_reason' => 'quota_exceeded',
            'success' => false,
        ]);
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

    public function test_admin_metrics_page_loads(): void
    {
        $user = $this->agentUser();
        $this->actingAs($user);

        $this->get(route('admin.agent-metrics.index'))
            ->assertOk()
            ->assertSee('métricas e custo LLM');
    }

    private function enableLlm(): void
    {
        config([
            'agent.llm.enabled' => true,
            'agent.llm.api_key' => 'test-key',
            'agent.llm.base_url' => 'https://api.openai.com/v1',
            'agent.chat.require_confirmation' => false,
        ]);
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
