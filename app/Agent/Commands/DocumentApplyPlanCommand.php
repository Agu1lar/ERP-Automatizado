<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandExecutor;
use App\Agent\AgentCommandResult;
use App\Agent\Contracts\SupportsDryRun;
use App\Models\User;

class DocumentApplyPlanCommand extends AbstractAgentCommand implements SupportsDryRun
{
    public static function name(): string
    {
        return 'document.apply_plan';
    }

    public static function description(): string
    {
        return 'Executa em sequência as ações propostas após análise de documento (cadastro, reserva, etc.).';
    }

    public function permission(): string
    {
        return 'agent.api';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['actions'],
            'properties' => [
                'actions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['command', 'params'],
                        'properties' => [
                            'command' => ['type' => 'string'],
                            'params' => ['type' => 'object'],
                            'label' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
    public function execute(array $input, User $user): \App\Agent\AgentCommandResult
    {
        $executor = app(AgentCommandExecutor::class);
        $actions = $input['actions'] ?? [];
        $lines = [];
        $lastCustomerId = null;
        $lastCustomerCpf = null;
        $nextSteps = [];

        foreach ($actions as $action) {
            $command = (string) ($action['command'] ?? '');
            $params = is_array($action['params'] ?? null) ? $action['params'] : [];

            if ($command === 'rental.reserve' && $lastCustomerId && empty($params['customer_id'])) {
                $params['customer_id'] = $lastCustomerId;
            }

            if ($command === 'rental.reserve' && $lastCustomerCpf && empty($params['customer_cpf_cnpj'])) {
                $params['customer_cpf_cnpj'] = $lastCustomerCpf;
            }

            $result = $executor->execute($command, $params, $user);

            if (! $result->ok) {
                return $this->failure(
                    "Plano interrompido em **{$command}**: {$result->message}",
                    $result->errorCode,
                );
            }

            $lines[] = '✓ '.$result->message;

            if ($command === 'customer.create' && ! empty($result->data['customer_id'])) {
                $lastCustomerId = (int) $result->data['customer_id'];
                $lastCustomerCpf = $result->data['customer']['cpf_cnpj'] ?? null;
            }

            $nextSteps = array_merge($nextSteps, $result->nextSteps);
        }

        return $this->success(
            "**Plano executado**\n\n".implode("\n", $lines),
            ['entity' => 'document_plan', 'steps' => count($actions)],
            array_slice($nextSteps, 0, 5),
        );
    }

    public function dryRun(array $input, User $user): AgentCommandResult
    {
        $labels = collect($input['actions'] ?? [])
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        $summary = $labels !== [] ? implode(' → ', $labels) : count($input['actions'] ?? []).' ação(ões)';

        return AgentCommandResult::preview(
            "Simulação do plano: {$summary}.",
            ['entity' => 'document_plan', 'dry_run' => true],
        );
    }
}
