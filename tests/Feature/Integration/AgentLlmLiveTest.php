<?php

namespace Tests\Feature\Integration;

use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\Domain\Agent\AgentLlmCall;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Testes E2E com LLM real — não mockam HTTP.
 *
 * Executar localmente:
 *   AGENT_LLM_E2E=true AGENT_LLM_ENABLED=true AGENT_LLM_API_KEY=sk-... php artisan test --group=llm-live
 */
#[Group('llm-live')]
class AgentLlmLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        if (! filter_var(env('AGENT_LLM_E2E', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Defina AGENT_LLM_E2E=true para rodar testes com LLM real.');
        }

        if (! filled(env('AGENT_LLM_API_KEY'))) {
            $this->markTestSkipped('Defina AGENT_LLM_API_KEY para rodar testes com LLM real.');
        }

        config([
            'agent.llm.enabled' => true,
            'agent.llm.api_key' => env('AGENT_LLM_API_KEY'),
            'agent.llm.base_url' => env('AGENT_LLM_BASE_URL', 'https://api.openai.com/v1'),
            'agent.llm.model' => env('AGENT_LLM_MODEL', 'gpt-4o-mini'),
            'agent.chat.require_confirmation' => false,
        ]);
    }

    public function test_live_llm_plain_reply_without_degraded_mode(): void
    {
        $user = $this->agentUser();

        $response = app(AgentChatOrchestrator::class)->handle(
            'Responda apenas: OK',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertFalse($response->llmDegraded, $response->reply);
        $this->assertNotSame('', trim($response->reply));

        $call = AgentLlmCall::query()
            ->where('call_type', AgentLlmCall::TYPE_CHAT_INTERPRET)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($call);
        $this->assertTrue($call->success);
        $this->assertGreaterThan(0, $call->total_tokens);
    }

    public function test_live_llm_can_route_finance_summary_tool(): void
    {
        $user = $this->agentUser();

        $response = app(AgentChatOrchestrator::class)->handle(
            'Mostre o resumo financeiro operacional de hoje',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertFalse($response->llmDegraded, $response->reply ?? '');
        $this->assertTrue(
            $response->executed || str_contains(mb_strtolower($response->reply ?? ''), 'financeiro'),
            'Esperado execução ou resposta sobre financeiro: '.($response->reply ?? '')
        );
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
