<?php

namespace Tests\Feature;

use App\Support\Agent\AgentActionPreviewBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsAgentApiFixtures;
use Tests\TestCase;

#[Group('agent')]
class AgentActionPreviewTest extends TestCase
{
    use BuildsAgentApiFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['agent.chat.require_confirmation' => true]);
    }

    public function test_write_command_confirmation_returns_structured_action_preview(): void
    {
        Sanctum::actingAs($this->agentUser());

        $response = $this->postJson('/api/agent/chat', [
            'message' => 'Registrar retorno da LOC-000099',
            'mode' => 'agent',
        ])
            ->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('command', 'rental.return')
            ->assertJsonStructure([
                'action_preview' => [
                    'ok',
                    'command',
                    'action_label',
                    'summary',
                    'parameters',
                    'effects',
                ],
                'dry_run_preview' => [
                    'action_preview',
                ],
            ]);

        $preview = $response->json('action_preview');
        $this->assertNotEmpty($preview['parameters']);
        $this->assertNotEmpty($preview['effects']);
    }

    public function test_preview_builder_formats_checklist_parameters(): void
    {
        $preview = app(AgentActionPreviewBuilder::class)->build(
            'maintenance.complete_field',
            [
                'order_codigo' => 'OS-TEST',
                'confirm_checklist_all' => true,
                'solucao' => 'Serviço concluído',
            ],
            $this->agentUser(),
        );

        $this->assertTrue($preview['ok']);
        $this->assertSame('maintenance.complete_field', $preview['command']);
        $this->assertNotEmpty(collect($preview['parameters'])->firstWhere('key', 'confirm_checklist_all'));
        $this->assertNotEmpty(collect($preview['parameters'])->firstWhere('key', 'solucao'));
    }
}
