<?php

namespace Tests\Feature;

use App\Agent\AgentCommandResult;
use App\Agent\Chat\AgentChatOptions;
use App\Agent\Chat\AgentChatOrchestrator;
use App\Enums\CopilotMode;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Agent\CopilotUserMessenger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopilotUserMessengerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_maps_technical_validation_errors_to_friendly_text(): void
    {
        $messenger = app(CopilotUserMessenger::class);

        $friendly = $messenger->forError(
            'Campo obrigatório ausente: rental_id',
            'validation_failed',
        );

        $this->assertStringNotContainsString('rental_id', $friendly);
        $this->assertStringNotContainsString('validation_failed', $friendly);
        $this->assertStringContainsString('locação', mb_strtolower($friendly));
    }

    public function test_command_label_replaces_internal_command_names(): void
    {
        $messenger = app(CopilotUserMessenger::class);

        $label = $messenger->commandLabel('quote.create');

        $this->assertNotSame('quote.create', $label);
        $this->assertNotEmpty($label);
    }

    public function test_pause_reply_does_not_expose_command_code(): void
    {
        config(['agent.chat.require_confirmation' => true]);

        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        $orchestrator = app(AgentChatOrchestrator::class);
        $session = app(\App\Agent\AgentSessionService::class)->resolve($user, 'web');

        $response = $orchestrator->handle(
            'Abrir contrato',
            $user,
            $session,
            new AgentChatOptions(mode: CopilotMode::Agent),
        );

        $this->assertTrue($response->requiresInput);
        $this->assertStringNotContainsString('quote.create', $response->reply);
        $this->assertStringNotContainsString('asset_codigo', $response->reply);
        $this->assertStringNotContainsString('{', $response->reply);
    }

    public function test_from_command_result_sanitizes_internal_errors(): void
    {
        $messenger = app(CopilotUserMessenger::class);

        $result = AgentCommandResult::failure(
            'Informe entry_id ou entry_codigo.',
            'validation_failed',
        );

        $friendly = $messenger->fromCommandResult($result);

        $this->assertStringNotContainsString('entry_id', $friendly);
        $this->assertStringNotContainsString('entry_codigo', $friendly);
    }
}
