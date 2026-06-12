<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\ProfitabilityReportService;
use App\Support\CopilotNavigationLinks;
use Carbon\Carbon;

class ReportFinancialAnalysisCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly ProfitabilityReportService $profitabilityReport,
    ) {}

    public static function name(): string
    {
        return 'report.financial_analysis';
    }

    public static function description(): string
    {
        return 'Resumo da análise financeira: faturamento, custos de manutenção e margem no período.';
    }

    public function permission(): string
    {
        return 'dashboard.analytics';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date_from' => ['type' => 'string', 'format' => 'date'],
                'date_to' => ['type' => 'string', 'format' => 'date'],
                'view_mode' => [
                    'type' => 'string',
                    'enum' => ['geral', 'category', 'asset'],
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 15],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $from = ! empty($input['date_from'])
            ? Carbon::parse($input['date_from'])->startOfDay()
            : now()->subDays(90)->startOfDay();
        $to = ! empty($input['date_to'])
            ? Carbon::parse($input['date_to'])->endOfDay()
            : now()->endOfDay();
        $viewMode = $input['view_mode'] ?? 'geral';
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 15);

        $summary = $this->profitabilityReport->summary($from, $to);

        $rows = match ($viewMode) {
            'category' => $this->profitabilityReport->byCategory($from, $to)->take($limit),
            'asset' => $this->profitabilityReport->byAsset($from, $to)->take($limit),
            default => collect(),
        };

        $margem = $summary['margem_percent'] !== null
            ? number_format($summary['margem_percent'], 1, ',', '.').'%'
            : '—';

        $message = "**Análise financeira** — {$from->format('d/m/Y')} a {$to->format('d/m/Y')}\n\n"
            .'• Faturamento: **R$ '.number_format($summary['faturamento'], 2, ',', '.')."**\n"
            .'• Custo manutenção: **R$ '.number_format($summary['custo_manutencao'], 2, ',', '.')."**\n"
            .'• Resultado: **R$ '.number_format($summary['resultado'], 2, ',', '.')."** (margem {$margem})\n"
            .'• Locações: **'.$summary['locacoes'].'** · OS concluídas: **'.$summary['os_concluidas'].'**';

        $data = [
            'entity' => 'report_financial_analysis',
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'view_mode' => $viewMode,
            'summary' => $summary,
        ];

        if ($rows->isNotEmpty()) {
            $data['rows'] = $rows->map(fn ($row) => (array) $row)->values()->all();
        }

        return $this->success(
            $message,
            $data,
            [
                ['label' => 'Abrir análise financeira', 'url' => CopilotNavigationLinks::financialAnalysis($from->toDateString(), $to->toDateString()), 'primary' => true],
            ],
        );
    }
}
