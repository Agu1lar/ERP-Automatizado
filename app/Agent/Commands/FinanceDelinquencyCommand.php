<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Support\CopilotNavigationLinks;
use App\Support\DelinquencyReportQuery;

class FinanceDelinquencyCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly DelinquencyReportQuery $delinquencyQuery,
    ) {}

    public static function name(): string
    {
        return 'finance.delinquency';
    }

    public static function description(): string
    {
        return 'Relatório de inadimplência com aging por cliente e títulos vencidos com encargos estimados.';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'q' => ['type' => 'string', 'description' => 'Filtrar por nome do cliente.'],
                'include_titles' => ['type' => 'boolean', 'description' => 'Incluir lista de títulos vencidos.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
        ];
    }

    public function execute(array $input, User $user): AgentCommandResult
    {
        $search = trim((string) ($input['q'] ?? ''));
        $search = $search !== '' ? $search : null;
        $limit = min(max((int) ($input['limit'] ?? 20), 1), 50);
        $includeTitles = (bool) ($input['include_titles'] ?? true);

        $summary = $this->delinquencyQuery->summary();
        $charges = $this->delinquencyQuery->chargeSummary($search);
        $customers = $this->delinquencyQuery->customersWithAging($search)->take($limit);

        $message = "**Inadimplência**\n\n"
            .'• Total em atraso: **R$ '.number_format($summary['total_atrasado'], 2, ',', '.')."**\n"
            .'• Clientes inadimplentes: **'.$summary['clientes']."**\n"
            .'• Aging: 0–30 **R$ '.number_format($summary['ate_30'], 2, ',', '.').'** · '
            .'31–60 **R$ '.number_format($summary['ate_60'], 2, ',', '.').'** · '
            .'61–90 **R$ '.number_format($summary['ate_90'], 2, ',', '.').'** · '
            .'+90 **R$ '.number_format($summary['acima_90'], 2, ',', '.')."**\n\n"
            .'Encargos estimados (multa/juros): **R$ '.number_format($charges['multa_valor'] + $charges['juros_valor'], 2, ',', '.').'**';

        $data = [
            'entity' => 'finance_delinquency',
            'summary' => $summary,
            'charge_summary' => $charges,
            'customers' => $customers->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'customer_nome' => $row->customer_nome,
                'total_aberto' => (float) $row->total_aberto,
                'total_atrasado' => (float) $row->total_atrasado,
                'ate_30' => (float) $row->ate_30,
                'ate_60' => (float) $row->ate_60,
                'ate_90' => (float) $row->ate_90,
                'acima_90' => (float) $row->acima_90,
                'titulos_atrasados' => (int) $row->titulos_atrasados,
            ])->values()->all(),
        ];

        if ($includeTitles) {
            $data['overdue_titles'] = $this->delinquencyQuery->overdueTitlesWithCharges($search)
                ->take($limit)
                ->map(fn ($row) => [
                    'codigo' => $row->title->codigo,
                    'customer_nome' => $row->title->customer?->nome,
                    'vencimento' => $row->title->vencimento?->toDateString(),
                    'dias_atraso' => $row->dias_atraso,
                    'valor_limpo' => (float) $row->valor_limpo,
                    'multa_valor' => (float) $row->multa_valor,
                    'juros_valor' => (float) $row->juros_valor,
                    'valor_total' => (float) $row->valor_total,
                ])
                ->values()
                ->all();
        }

        return $this->success(
            $message,
            $data,
            [
                ['label' => 'Abrir inadimplência', 'url' => route('finance.delinquency'), 'primary' => true],
                ['label' => 'Títulos a receber', 'url' => CopilotNavigationLinks::financeReceivables()],
            ],
        );
    }
}
