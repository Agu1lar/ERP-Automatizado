<?php

namespace App\Support\Agent;

use App\Agent\AgentCommandExecutor;
use App\Agent\AgentCommandRegistry;
use App\Agent\AgentCommandResult;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\Domain\Agent\AgentSession;
use App\Models\User;
use App\Services\MaintenanceOrderService;

class AgentActionPreviewBuilder
{
    public function __construct(
        private readonly AgentCommandRegistry $registry,
        private readonly AgentCommandRequirementsRegistry $requirements,
        private readonly AgentCommandExecutor $executor,
        private readonly CopilotUserMessenger $messenger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(string $command, array $input, User $user, ?AgentSession $session = null): array
    {
        $actionLabel = $this->messenger->commandLabel($command);
        $parameters = $this->buildParameters($command, $input);
        $dryRun = $this->runDryRun($command, $input, $user, $session);

        if ($dryRun !== null && ! $dryRun->ok) {
            return [
                'ok' => false,
                'command' => $command,
                'action_label' => $actionLabel,
                'summary' => $this->messenger->forError($dryRun->message, $dryRun->errorCode),
                'parameters' => $parameters,
                'effects' => [],
                'targets' => [],
                'warnings' => [$this->messenger->forError($dryRun->message, $dryRun->errorCode)],
                'dry_run' => true,
            ];
        }

        $effects = $this->buildEffects($command, $input, $dryRun?->data ?? []);
        $targets = $this->buildTargets($command, $input, $dryRun?->data ?? []);
        $summary = $this->buildSummary($command, $actionLabel, $parameters, $effects, $dryRun);

        return [
            'ok' => true,
            'command' => $command,
            'action_label' => $actionLabel,
            'summary' => $summary,
            'parameters' => $parameters,
            'effects' => $effects,
            'targets' => $targets,
            'warnings' => [],
            'dry_run' => $dryRun?->dryRun ?? false,
        ];
    }

    /** @param  array<string, mixed>  $input */
    private function runDryRun(string $command, array $input, User $user, ?AgentSession $session): ?AgentCommandResult
    {
        $cmd = $this->registry->get($command);

        if (! $cmd instanceof SupportsDryRun) {
            return null;
        }

        return $this->executor->execute($command, $input, $user, $session, dryRun: true);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{key: string, label: string, value: string}>
     */
    private function buildParameters(string $command, array $input): array
    {
        $labels = $this->fieldLabelsForCommand($command);
        $alternativeGroups = $this->alternativeGroupsForCommand($command);
        $parameters = [];
        $usedGroups = [];

        foreach ($input as $key => $value) {
            if ($this->shouldSkipParameter($key, $value)) {
                continue;
            }

            $groupKey = $this->alternativeGroupKey($key, $alternativeGroups);

            if ($groupKey !== null) {
                if (isset($usedGroups[$groupKey])) {
                    continue;
                }

                $usedGroups[$groupKey] = true;
            }

            if ($key === 'checklist' && is_array($value)) {
                foreach ($value as $itemKey => $checked) {
                    $label = MaintenanceOrderService::CHECKLIST_CAMPO[$itemKey]
                        ?? AgentInputFieldLabels::label((string) $itemKey);

                    $parameters[] = [
                        'key' => 'checklist.'.$itemKey,
                        'label' => $label,
                        'value' => $this->formatValue($checked),
                    ];
                }

                continue;
            }

            if ($key === 'confirm_checklist_all' && $value === true) {
                $parameters[] = [
                    'key' => $key,
                    'label' => AgentInputFieldLabels::label($key),
                    'value' => 'Todos os itens confirmados',
                ];

                continue;
            }

            $parameters[] = [
                'key' => (string) $key,
                'label' => $labels[$key] ?? AgentInputFieldLabels::label((string) $key),
                'value' => $this->formatValue($value),
            ];
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $dryRunData
     * @return list<array{label: string, before?: string, after: string}>
     */
    private function buildEffects(string $command, array $input, array $dryRunData): array
    {
        $effects = [];

        if (isset($dryRunData['entry']) && is_array($dryRunData['entry'])) {
            $entry = $dryRunData['entry'];

            if (isset($entry['status_atual'], $entry['status_novo'])) {
                $effects[] = [
                    'label' => 'Status da pendência',
                    'before' => (string) $entry['status_atual'],
                    'after' => (string) $entry['status_novo'],
                ];
            }

            if (isset($entry['valor_car'])) {
                $effects[] = [
                    'label' => 'Valor a faturar',
                    'after' => 'R$ '.number_format((float) $entry['valor_car'], 2, ',', '.'),
                ];
            }

            if (! empty($entry['autorizar_antes'])) {
                $effects[] = [
                    'label' => 'Autorização',
                    'after' => 'Autorizar e faturar em sequência',
                ];
            }
        }

        if (isset($dryRunData['status_from'], $dryRunData['status_to'])) {
            $effects[] = [
                'label' => 'Status do patrimônio',
                'before' => (string) $dryRunData['status_from'],
                'after' => (string) $dryRunData['status_to'],
            ];
        }

        if (isset($dryRunData['asset_codigo'], $dryRunData['new_status']) && ! isset($dryRunData['status_from'])) {
            $effects[] = [
                'label' => 'Patrimônio '.$dryRunData['asset_codigo'],
                'after' => (string) $dryRunData['new_status'],
            ];
        }

        if (isset($dryRunData['from_asset'], $dryRunData['to_asset'])) {
            $effects[] = [
                'label' => 'Patrimônio na locação',
                'before' => (string) $dryRunData['from_asset'],
                'after' => (string) $dryRunData['to_asset'],
            ];
        }

        if ($effects === []) {
            $effects[] = [
                'label' => 'Resultado esperado',
                'after' => $this->messenger->commandLabel($command),
            ];
        }

        return $effects;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $dryRunData
     * @return list<array{type: string, label: string, codigo?: string}>
     */
    private function buildTargets(string $command, array $input, array $dryRunData): array
    {
        $targets = [];

        $map = [
            'rental_codigo' => 'rental',
            'asset_codigo' => 'asset',
            'order_codigo' => 'maintenance_order',
            'quote_codigo' => 'quote',
            'entry_codigo' => 'billing_entry',
            'title_codigo' => 'receivable',
            'customer_name' => 'customer',
        ];

        foreach ($map as $field => $type) {
            $value = $dryRunData[$field] ?? $input[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $targets[] = [
                'type' => $type,
                'label' => trim($value),
                'codigo' => trim($value),
            ];
        }

        if (isset($dryRunData['entry']['codigo'])) {
            $targets[] = [
                'type' => 'billing_entry',
                'label' => (string) $dryRunData['entry']['codigo'],
                'codigo' => (string) $dryRunData['entry']['codigo'],
            ];
        }

        return $targets;
    }

    /**
     * @param  list<array{key: string, label: string, value: string}>  $parameters
     * @param  list<array{label: string, before?: string, after: string}>  $effects
     */
    private function buildSummary(
        string $command,
        string $actionLabel,
        array $parameters,
        array $effects,
        ?AgentCommandResult $dryRun,
    ): string {
        if ($dryRun !== null && filled($dryRun->message)) {
            return $this->messenger->sanitizeReply($dryRun->message);
        }

        $highlights = collect($parameters)
            ->take(3)
            ->map(fn (array $param) => $param['label'].': '.$param['value'])
            ->implode(' · ');

        if ($highlights !== '') {
            return $actionLabel.' — '.$highlights;
        }

        $firstEffect = $effects[0]['after'] ?? $actionLabel;

        return $actionLabel.' — '.$firstEffect;
    }

    /** @return array<string, string> */
    private function fieldLabelsForCommand(string $command): array
    {
        $labels = AgentInputFieldLabels::all();
        $definition = $this->requirements->definition($command);

        if ($definition === null) {
            return $labels;
        }

        foreach ($definition['required_groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $labels[$field] = $group['label'];
            }
        }

        foreach ($definition['recommended'] as $field) {
            $labels[$field['key']] = $field['label'];
        }

        return $labels;
    }

    /**
     * @return array<string, list<string>>
     */
    private function alternativeGroupsForCommand(string $command): array
    {
        $definition = $this->requirements->definition($command);

        if ($definition === null) {
            return [];
        }

        $groups = [];

        foreach ($definition['required_groups'] as $index => $group) {
            if (count($group['fields']) > 1) {
                $groups['group_'.$index] = $group['fields'];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, list<string>>  $groups
     */
    private function alternativeGroupKey(string $key, array $groups): ?string
    {
        foreach ($groups as $groupKey => $fields) {
            if (in_array($key, $fields, true)) {
                return $groupKey;
            }
        }

        return null;
    }

    private function shouldSkipParameter(string $key, mixed $value): bool
    {
        if (in_array($key, ['dry_run'], true)) {
            return true;
        }

        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        return false;
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return trim((string) $value);
    }
}
