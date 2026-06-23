<?php

namespace App\Jobs;

use App\Agent\AgentCommandExecutor;
use App\Enums\AgentTaskStatus;
use App\Models\Domain\Agent\AgentTask;
use App\Services\AgentTaskService;
use App\Support\Agent\AgentConcurrencyGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAgentTaskJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $agentTaskId,
    ) {}

    public function handle(
        AgentCommandExecutor $executor,
        AgentConcurrencyGuard $concurrency,
        AgentTaskService $taskService,
    ): void {
        $task = AgentTask::query()->with('user')->find($this->agentTaskId);

        if (! $task || $task->status !== AgentTaskStatus::Queued->value) {
            return;
        }

        if (! $task->markRunning()) {
            return;
        }

        $user = $task->user;
        $results = [];

        foreach ($task->steps as $index => $step) {
            $task->refresh();

            if (in_array($task->status, [
                AgentTaskStatus::Conflict->value,
                AgentTaskStatus::Cancelled->value,
            ], true)) {
                return;
            }

            $command = (string) ($step['command'] ?? '');
            $params = is_array($step['params'] ?? null) ? $step['params'] : [];

            if ($concurrency->isMutatingCommand($command)) {
                $check = $concurrency->verifySnapshots($task->resource_snapshots ?? []);

                if (! ($check['ok'] ?? false)) {
                    $task->markConflict($check['reason'] ?? 'Conflito de concorrência.');

                    return;
                }
            }

            $result = $executor->execute($command, $params, $user, $task->session, agentTask: $task);

            $results[] = [
                'command' => $command,
                'ok' => $result->ok,
                'message' => $result->message,
            ];

            $task->update([
                'current_step' => $index + 1,
                'step_results' => $results,
            ]);

            if (! $result->ok) {
                $task->markFailed($result->message);

                return;
            }

            if ($concurrency->isMutatingCommand($command)) {
                $taskService->refreshSnapshotsAfterStep($task, $command, $params);
            }
        }

        $task->markCompleted();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAgentTaskJob failed', [
            'task_id' => $this->agentTaskId,
            'error' => $exception->getMessage(),
        ]);

        AgentTask::query()->whereKey($this->agentTaskId)->first()?->markFailed(
            'Falha interna ao processar tarefa do agente.',
        );
    }
}
