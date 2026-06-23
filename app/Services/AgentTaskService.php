<?php

namespace App\Services;

use App\Enums\AgentTaskStatus;
use App\Jobs\ProcessAgentTaskJob;
use App\Models\Domain\Agent\AgentSession;
use App\Models\Domain\Agent\AgentTask;
use App\Models\Domain\Agent\AgentTaskResource;
use App\Models\User;
use App\Support\ActiveOperatingCompany;
use App\Support\Agent\AgentConcurrencyGuard;
use App\Support\Agent\AgentResourceReference;
use Illuminate\Support\Facades\DB;

class AgentTaskService
{
    public function __construct(
        private readonly AgentConcurrencyGuard $concurrency,
    ) {}

    /**
     * @param  list<array{command: string, params?: array<string, mixed>, label?: string}>  $steps
     */
    public function queue(
        User $user,
        array $steps,
        string $title,
        ?AgentSession $session = null,
        ?string $idempotencyKey = null,
    ): AgentTask {
        $steps = array_values(array_filter($steps, fn ($s) => ! empty($s['command'])));

        if ($steps === []) {
            throw new \InvalidArgumentException('Plano vazio.');
        }

        if ($idempotencyKey) {
            $existing = AgentTask::query()
                ->where('operating_company_id', ActiveOperatingCompany::id())
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [
                    AgentTaskStatus::Queued->value,
                    AgentTaskStatus::Running->value,
                    AgentTaskStatus::Completed->value,
                ])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $steps, $title, $session, $idempotencyKey) {
            $snapshots = $this->concurrency->collectSnapshotsForPlan($steps);

            $task = AgentTask::create([
                'user_id' => $user->id,
                'operating_company_id' => ActiveOperatingCompany::id(),
                'agent_session_id' => $session?->id,
                'status' => AgentTaskStatus::Queued->value,
                'title' => $title,
                'total_steps' => count($steps),
                'steps' => $steps,
                'resource_snapshots' => $snapshots,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($snapshots as $snapshot) {
                AgentTaskResource::create([
                    'agent_task_id' => $task->id,
                    'resource_type' => $snapshot['type'],
                    'resource_id' => $snapshot['id'],
                    'snapshot_updated_at' => isset($snapshot['snapshot'])
                        ? \Carbon\Carbon::parse($snapshot['snapshot'])
                        : null,
                ]);
            }

            if ($this->shouldRunInline()) {
                ProcessAgentTaskJob::dispatchSync($task->id);
            } else {
                ProcessAgentTaskJob::dispatch($task->id);
            }

            return $task->fresh();
        });
    }

    private function shouldRunInline(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        return (bool) config('agent.tasks.run_inline_in_local', false);
    }

    public function cancel(AgentTask $task): AgentTask
    {
        if (! $task->isCancellable()) {
            return $task;
        }

        $task->markCancelled();

        return $task->fresh();
    }

    /** Chamado quando usuário altera recurso no ERP — invalida tarefas em fila/execução. */
    public function notifyResourceChanged(string $type, int $id): void
    {
        $links = AgentTaskResource::query()
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->whereHas('task', fn ($q) => $q->whereIn('status', [
                AgentTaskStatus::Queued->value,
                AgentTaskStatus::Running->value,
            ]))
            ->with('task')
            ->get();

        foreach ($links as $link) {
            $model = match ($type) {
                'rental' => \App\Models\Domain\Rental\Rental::query()->find($id),
                'asset' => \App\Models\Domain\Fleet\Asset::query()->find($id),
                'customer' => \App\Models\Domain\Customer\Customer::query()->find($id),
                'maintenance_order' => \App\Models\Domain\Maintenance\MaintenanceOrder::query()->find($id),
                'billing_entry' => \App\Models\Domain\Rental\RentalBillingQueueEntry::query()->find($id),
                'receivable_title' => \App\Models\Domain\Finance\ReceivableTitle::query()->find($id),
                default => null,
            };

            if (! $model || ! $link->snapshot_updated_at || ! $model->updated_at) {
                continue;
            }

            if ($link->snapshot_updated_at->getTimestamp() !== $model->updated_at->getTimestamp()) {
                $link->task?->markConflict(
                    "O recurso {$type}:{$id} foi alterado manualmente enquanto o agente executava.",
                );
            }
        }
    }

    public function refreshSnapshotsAfterStep(AgentTask $task, string $command, array $input): void
    {
        $resources = $this->concurrency->resourcesForCommand($command, $input);

        foreach ($resources as $resource) {
            $snapshots = $this->concurrency->captureSnapshots([$resource]);
            $fresh = $snapshots[0] ?? null;

            if (! $fresh) {
                continue;
            }

            AgentTaskResource::query()
                ->where('agent_task_id', $task->id)
                ->where('resource_type', $fresh['type'])
                ->where('resource_id', $fresh['id'])
                ->update([
                    'snapshot_updated_at' => \Carbon\Carbon::parse($fresh['snapshot']),
                ]);

            $all = collect($task->resource_snapshots ?? []);
            $key = AgentResourceReference::key($fresh);
            $updated = $all->map(function ($entry) use ($fresh, $key) {
                if (AgentResourceReference::key($entry) === $key) {
                    $entry['snapshot'] = $fresh['snapshot'];
                }

                return $entry;
            });

            $task->update(['resource_snapshots' => $updated->values()->all()]);
        }
    }
}
