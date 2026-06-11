<?php

namespace Tests\Feature;

use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentLlmFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_llm_quota_failure_notifies_user_and_uses_heuristic_fallback(): void
    {
        config([
            'agent.llm.enabled' => true,
            'agent.llm.api_key' => 'test-key',
            'agent.llm.base_url' => 'https://api.openai.com/v1',
            'agent.chat.require_confirmation' => true,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        $user = $this->agentUser();
        $orchestrator = app(AgentChatOrchestrator::class);

        $response = $orchestrator->handle(
            'Abrir contrato',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $this->assertTrue($response->llmDegraded);
        $this->assertStringContainsString('Inteligência operacional indisponível', $response->reply);
        $this->assertStringContainsString('limite', mb_strtolower($response->reply));
        $this->assertTrue($response->requiresInput || $response->requiresConfirmation);
    }

    public function test_llm_success_clears_degraded_flag(): void
    {
        config([
            'agent.llm.enabled' => true,
            'agent.llm.api_key' => 'test-key',
            'agent.llm.base_url' => 'https://api.openai.com/v1',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Sou a assistente da Acesso Equipamentos, desenvolvida por José.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->agentUser();
        $orchestrator = app(AgentChatOrchestrator::class);

        $response = $orchestrator->handle(
            'Quem te criou?',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertFalse($response->llmDegraded);
        $this->assertStringContainsString('José', $response->reply);
    }

    public function test_heuristic_without_llm_config_does_not_show_degraded_notice(): void
    {
        config(['agent.llm.enabled' => false]);

        $user = $this->agentUser();
        $orchestrator = app(AgentChatOrchestrator::class);

        $response = $orchestrator->handle(
            'Resumo financeiro',
            $user,
            null,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        $this->assertFalse($response->llmDegraded);
        $this->assertStringNotContainsString('Inteligência operacional indisponível', $response->reply);
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
