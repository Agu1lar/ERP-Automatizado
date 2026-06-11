<?php

namespace App\Support\Agent;

use App\Agent\AgentCommandRegistry;
use App\Agent\Contracts\AgentCommand;
use App\Enums\AgentCommandKind;

class AgentConcurrencyGuard
{
    public function __construct(
        private readonly AgentCommandRegistry $registry,
    ) {}

    /** @param  array<string, mixed>  $input @return list<AgentResourceShape> */
    public function resourcesForCommand(string $command, array $input): array
    {
        if (! $this->registry->has($command)) {
            return [];
        }

        $cmd = $this->registry->get($command);

        if (! method_exists($cmd, 'affectedResources')) {
            return [];
        }

        return $cmd->affectedResources($input);
    }

    /** @param  list<AgentResourceShape>  $resources */
    public function isMutatingCommand(string $command): bool
    {
        if (! $this->registry->has($command)) {
            return false;
        }

        $cmd = $this->registry->get($command);

        if (method_exists($cmd, 'commandKind')) {
            return $cmd->commandKind() === AgentCommandKind::Write;
        }

        return true;
    }

    /**
     * @param  list<AgentResourceShape>  $snapshots  snapshot string keyed by resource key
     * @return array{ok: bool, reason?: string, resource?: string}
     */
    public function verifySnapshots(array $snapshots): array
    {
        foreach ($snapshots as $entry) {
            $model = $this->resolveModel($entry['type'], (int) $entry['id']);

            if (! $model) {
                continue;
            }

            $current = AgentResourceReference::snapshotFromModel($model);
            $expected = $entry['snapshot'] ?? null;

            if ($expected !== null && $expected !== $current) {
                return [
                    'ok' => false,
                    'reason' => 'Recurso alterado enquanto a tarefa aguardava execução.',
                    'resource' => AgentResourceReference::key($entry),
                ];
            }
        }

        return ['ok' => true];
    }

    /** @param  list<AgentResourceShape>  $resources @return list<AgentResourceShape> */
    public function captureSnapshots(array $resources): array
    {
        $captured = [];

        foreach ($resources as $resource) {
            $model = $this->resolveModel($resource['type'], (int) $resource['id']);

            if (! $model) {
                continue;
            }

            $captured[] = [
                'type' => $resource['type'],
                'id' => (int) $resource['id'],
                'snapshot' => AgentResourceReference::snapshotFromModel($model),
            ];
        }

        return $captured;
    }

    /** @param  list<AgentResourceShape>  $planSteps  @return list<AgentResourceShape> */
    public function collectSnapshotsForPlan(array $planSteps): array
    {
        $merged = [];

        foreach ($planSteps as $step) {
            $command = (string) ($step['command'] ?? '');
            $params = is_array($step['params'] ?? null) ? $step['params'] : [];

            if ($command === '' || ! $this->isMutatingCommand($command)) {
                continue;
            }

            foreach ($this->resourcesForCommand($command, $params) as $resource) {
                $merged[AgentResourceReference::key($resource)] = $resource;
            }
        }

        return $this->captureSnapshots(array_values($merged));
    }

    private function resolveModel(string $type, int $id): ?object
    {
        return match ($type) {
            'rental' => \App\Models\Domain\Rental\Rental::query()->find($id),
            'asset' => \App\Models\Domain\Fleet\Asset::query()->find($id),
            'customer' => \App\Models\Domain\Customer\Customer::query()->find($id),
            'maintenance_order' => \App\Models\Domain\Maintenance\MaintenanceOrder::query()->find($id),
            'billing_entry' => \App\Models\Domain\Rental\RentalBillingQueueEntry::query()->find($id),
            'receivable_title' => \App\Models\Domain\Finance\ReceivableTitle::query()->find($id),
            default => null,
        };
    }
}
