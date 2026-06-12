<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\AgentCopilotMetricsService;
use App\Services\AgentLlmUsageService;
use App\Support\CopilotNavigationLinks;
use Carbon\Carbon;

class AgentMetricsCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly AgentCopilotMetricsService $metricsService,
        private readonly AgentLlmUsageService $usageService,
    ) {}

    public static function name(): string
    {
        return 'agent.metrics';
    }

    public static function description(): string
    {
        return 'Métricas do copiloto: uso LLM (tokens/custo), taxa de fallback, comandos mais usados e falhas por permissão.';
    }

    public function permission(): string
    {
        return 'audit.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to' => ['type' => 'string', 'format' => 'date'],
                'user_id' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $from = ! empty($input['date_from'])
            ? Carbon::parse($input['date_from'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = ! empty($input['date_to'])
            ? Carbon::parse($input['date_to'])->endOfDay()
            : now()->endOfDay();

        $summary = $this->metricsService->summary(
            $from,
            $to,
            operatingCompanyId: null,
            userId: ! empty($input['user_id']) ? (int) $input['user_id'] : null,
        );

        $quota = $this->usageService->quotaStatus($user);
        $llm = $summary['llm'];
        $commands = $summary['commands'];
        $today = $summary['today'];

        $message = "**Métricas do copiloto** — {$from->format('d/m/Y')} a {$to->format('d/m/Y')}\n\n"
            ."**Hoje:** {$today['tokens']} tokens · ~US$ ".number_format($today['estimated_cost_usd'], 4, ',', '.')
            ." · {$today['llm_calls']} chamada(s) LLM · {$today['fallback_events']} fallback(s)\n"
            .'• Taxa de sucesso LLM: **'.($llm['success_rate_percent'] !== null ? $llm['success_rate_percent'].'%' : '—')."**\n"
            .'• Taxa de fallback: **'.($llm['fallback_rate_percent'] !== null ? $llm['fallback_rate_percent'].'%' : '—')."**\n"
            .'• Comandos executados: **'.$commands['executed']."** · negados por permissão: **{$commands['permission_denied']}**\n";

        if ($quota['limit'] !== null) {
            $message .= '• Sua quota diária: **'.$quota['used'].' / '.$quota['limit']."** tokens ({$quota['scope']})\n";
        }

        return $this->success(
            $message,
            [
                'entity' => 'agent_metrics',
                'summary' => $summary,
                'quota' => $quota,
            ],
            [
                ['label' => 'Painel de métricas', 'url' => CopilotNavigationLinks::adminAgentMetrics(), 'primary' => true],
                ['label' => 'Logs de comandos', 'url' => CopilotNavigationLinks::adminAgentLogs()],
            ],
        );
    }
}
