<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\CommercialReportService;
use App\Support\CopilotNavigationLinks;
use Carbon\Carbon;

class ReportCommercialCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly CommercialReportService $commercialReport,
    ) {}

    public static function name(): string
    {
        return 'report.commercial';
    }

    public static function description(): string
    {
        return 'Resumo do relatório comercial: faturamento por tipo de equipamento ou responsável comercial no período.';
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
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['model', 'category', 'user'],
                    'description' => 'Agrupamento: modelo, categoria ou comercial.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $from = ! empty($input['date_from'])
            ? Carbon::parse($input['date_from'])->startOfDay()
            : now()->startOfMonth();
        $to = ! empty($input['date_to'])
            ? Carbon::parse($input['date_to'])->endOfDay()
            : now()->endOfDay();
        $groupBy = $input['group_by'] ?? 'model';
        $limit = min(max((int) ($input['limit'] ?? 10), 1), 20);

        $total = $this->commercialReport->totalRevenueInPeriod($from, $to);
        $rows = $groupBy === 'user'
            ? $this->commercialReport->revenueByCommercialUser($from, $to)
            : $this->commercialReport->revenueByEquipmentType($from, $to, $groupBy);

        $top = $rows->take($limit);

        $message = "**Relatório comercial** — {$from->format('d/m/Y')} a {$to->format('d/m/Y')}\n\n"
            .'• Faturamento total (locações concluídas): **R$ '.number_format($total, 2, ',', '.')."**\n"
            .'• Top '.min($limit, $rows->count()).' grupo(s) abaixo.';

        return $this->success(
            $message,
            [
                'entity' => 'report_commercial',
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'group_by' => $groupBy,
                'total_revenue' => $total,
                'rows' => $top->map(fn ($row) => [
                    'grupo_id' => $row->grupo_id,
                    'grupo_nome' => $row->grupo_nome,
                    'total_locacoes' => $row->total_locacoes,
                    'faturamento_total' => $row->faturamento_total,
                    'ticket_medio' => $row->ticket_medio,
                ])->values()->all(),
            ],
            [
                ['label' => 'Abrir relatório comercial', 'url' => CopilotNavigationLinks::commercialReport($from->toDateString(), $to->toDateString()), 'primary' => true],
            ],
        );
    }
}
