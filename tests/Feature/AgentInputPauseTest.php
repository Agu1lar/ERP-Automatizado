<?php

namespace Tests\Feature;

use App\Agent\AgentSessionService;
use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Agent\AgentInputCompletionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentInputPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['agent.llm.enabled' => false, 'agent.llm.api_key' => null]);
        Http::fake();
    }

    public function test_assess_detects_missing_fields_for_quote_create(): void
    {
        $service = app(AgentInputCompletionService::class);

        $assessment = $service->assess('quote.create', []);

        $this->assertFalse($assessment->isComplete());
        $this->assertCount(2, $assessment->missing);
        $this->assertNotEmpty($assessment->recommended);
    }

    public function test_merge_from_message_extracts_asset_and_customer(): void
    {
        $service = app(AgentInputCompletionService::class);

        $merged = $service->mergeFromMessage(
            'Patrimônio PAT-ABC-1 para cliente Construtora Beta, obra Rua Central 100',
            [],
            'quote.create',
        );

        $this->assertSame('PAT-ABC-1', $merged['asset_codigo']);
        $this->assertSame('Construtora Beta', $merged['customer_name']);
        $this->assertSame('Rua Central 100', $merged['local_obra']);
    }

    public function test_orchestrator_pauses_quote_create_when_incomplete(): void
    {
        config(['agent.chat.require_confirmation' => true]);

        $user = $this->agentUser();
        $session = app(AgentSessionService::class)->resolve($user, 'web');
        $orchestrator = app(AgentChatOrchestrator::class);

        $response = $orchestrator->handle(
            'Abrir contrato',
            $user,
            $session,
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $this->assertTrue($response->requiresInput);
        $this->assertSame('quote.create', $response->command);
        $this->assertNotNull($session->fresh()->pending_execution);
    }

    public function test_orchestrator_resumes_after_user_provides_missing_data(): void
    {
        config(['agent.chat.require_confirmation' => true]);

        $user = $this->agentUser();
        $sessionService = app(AgentSessionService::class);
        $session = $sessionService->resolve($user, 'web');
        $orchestrator = app(AgentChatOrchestrator::class);

        $orchestrator->handle(
            'Abrir contrato',
            $user,
            $session,
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $response = $orchestrator->handle(
            'Patrimônio PAT-RES-1, cliente Obra Norte Ltda, local obra Av. Brasil 500',
            $user,
            $session->fresh(),
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $this->assertTrue($response->requiresConfirmation);
        $this->assertSame('quote.create', $response->command);
        $this->assertSame('PAT-RES-1', $response->commandInput['asset_codigo'] ?? null);
        $this->assertSame('Obra Norte Ltda', $response->commandInput['customer_name'] ?? null);
        $this->assertNull($session->fresh()->pending_execution);
    }

    public function test_cancel_clears_pending_execution(): void
    {
        $user = $this->agentUser();
        $sessionService = app(AgentSessionService::class);
        $session = $sessionService->resolve($user, 'web');
        $orchestrator = app(AgentChatOrchestrator::class);

        $orchestrator->handle(
            'Abrir contrato',
            $user,
            $session,
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $response = $orchestrator->handle(
            'cancelar',
            $user,
            $session->fresh(),
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $this->assertStringContainsString('cancelada', mb_strtolower($response->reply));
        $this->assertNull($session->fresh()->pending_execution);
    }

    public function test_heuristic_maps_abrir_contrato_to_quote_create(): void
    {
        $parsed = app(\App\Agent\Chat\AgentHeuristicParser::class)->parse('Preciso abrir um contrato de locação');

        $this->assertSame('quote.create', $parsed['command'] ?? null);
    }

    private function agentUser(): User
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
