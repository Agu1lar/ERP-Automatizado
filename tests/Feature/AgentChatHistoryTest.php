<?php

namespace Tests\Feature;

use App\Agent\AgentSessionService;
use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('agent')]
class AgentChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_llm_receives_prior_session_messages(): void
    {
        config([
            'agent.llm.enabled' => true,
            'agent.llm.api_key' => 'test-key',
            'agent.chat.max_history_messages' => 10,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Entendi o contexto.']],
                ],
                'usage' => ['total_tokens' => 50],
            ], 200),
        ]);

        $user = $this->agentUser();
        $session = app(AgentSessionService::class)->resolve($user, 'api');
        $sessions = app(AgentSessionService::class);

        $sessions->logMessage($session, 'user', 'Quantas betoneiras estão locadas?');
        $sessions->logMessage($session, 'assistant', 'Há 3 betoneiras locadas no momento.');

        app(AgentChatOrchestrator::class)->handle(
            'E qual o cliente da LOC-000123?',
            $user,
            $session,
            new AgentChatOptions(mode: CopilotMode::Ask),
        );

        Http::assertSent(function ($request) {
            $messages = $request->data()['messages'] ?? [];

            $userMessages = collect($messages)
                ->where('role', 'user')
                ->pluck('content')
                ->all();

            return in_array('Quantas betoneiras estão locadas?', $userMessages, true)
                && in_array('E qual o cliente da LOC-000123?', $userMessages, true);
        });
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
