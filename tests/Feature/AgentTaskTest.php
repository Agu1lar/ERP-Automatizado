<?php

namespace Tests\Feature;

use App\Enums\AgentTaskStatus;
use App\Enums\AssetStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAgentTaskJob;
use App\Models\Domain\Agent\AgentTask;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Services\AgentTaskService;
use App\Services\AssetStatusService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_can_queue_background_task_via_api(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->agentUser());

        $this->postJson('/api/agent/tasks', [
            'title' => 'Teste financeiro',
            'steps' => [
                ['command' => 'finance.summary', 'params' => []],
            ],
        ])
            ->assertStatus(202)
            ->assertJsonPath('status', AgentTaskStatus::Queued->value);

        Queue::assertPushed(ProcessAgentTaskJob::class);
    }

    public function test_background_task_runs_read_command(): void
    {
        Sanctum::actingAs($this->agentUser());

        $task = app(AgentTaskService::class)->queue(
            $this->agentUser(),
            [['command' => 'finance.summary', 'params' => []]],
            'Resumo',
        );

        ProcessAgentTaskJob::dispatchSync($task->id);

        $task->refresh();
        $this->assertSame(AgentTaskStatus::Completed->value, $task->status);
        $this->assertSame(1, $task->current_step);
    }

    public function test_user_edit_marks_task_as_conflict(): void
    {
        $user = $this->agentUser();
        $customer = Customer::create([
            'nome' => 'Cliente Concorrência',
            'cpf_cnpj' => '39053344705',
            'ativo' => true,
        ]);

        $task = AgentTask::create([
            'user_id' => $user->id,
            'status' => AgentTaskStatus::Queued->value,
            'title' => 'Cadastro',
            'total_steps' => 1,
            'steps' => [['command' => 'customer.create', 'params' => ['nome' => 'X', 'cpf_cnpj' => '39053344705']]],
            'resource_snapshots' => [['type' => 'customer', 'id' => $customer->id, 'snapshot' => $customer->updated_at->toIso8601String()]],
        ]);

        $task->resources()->create([
            'resource_type' => 'customer',
            'resource_id' => $customer->id,
            'snapshot_updated_at' => $customer->updated_at,
        ]);

        $this->travel(2)->seconds();
        $customer->update(['nome' => 'Cliente Concorrência Alterado']);

        app(AgentTaskService::class)->notifyResourceChanged('customer', $customer->id);

        $task->refresh();
        $this->assertSame(AgentTaskStatus::Conflict->value, $task->status);
    }

    public function test_manifest_includes_task_endpoints(): void
    {
        Sanctum::actingAs($this->agentUser());

        $this->getJson('/api/agent/manifest')
            ->assertOk()
            ->assertJsonPath('version', '1.4')
            ->assertJsonStructure([
                'modes' => ['ask', 'agent', 'surfaces', 'navigation'],
                'commands_by_surface' => ['visualization', 'execution'],
                'task_endpoints',
                'concurrency',
            ]);
    }

    private function agentUser(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['ativo' => true]);
        $user->assignRole(UserRole::Gestor->value);
        $user->givePermissionTo('agent.api');

        return $user;
    }
}
