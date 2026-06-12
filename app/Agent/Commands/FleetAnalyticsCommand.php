<?php

namespace App\Agent\Commands;

use App\Agent\AgentCommandResult;
use App\Models\User;
use App\Services\FleetAnalyticsService;
use App\Support\CopilotNavigationLinks;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FleetAnalyticsCommand extends AbstractReadAgentCommand
{
    public function __construct(
        private readonly FleetAnalyticsService $fleetAnalytics,
    ) {}

    public static function name(): string
    {
        return 'fleet.analytics';
    }

    public static function description(): string
    {
        return 'Indicadores de frota: ocupação, rentabilidade por patrimônio, ROI/payback e sugestões de desinvestimento.';
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
                'view' => [
                    'type' => 'string',
                    'enum' => ['occupancy', 'profitability', 'investment', 'divestment'],
                    'description' => 'occupancy=ocupação; profitability=resultado por patrimônio; investment=ROI/payback; divestment=sugestões de venda',
                ],
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['asset', 'category', 'model'],
                ],
                'region' => [
                    'type' => 'string',
                    'enum' => ['bh', 'rmbh', 'interior'],
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
        $view = $input['view'] ?? 'occupancy';
        $groupBy = $input['group_by'] ?? 'category';
        $region = filled($input['region'] ?? null) ? (string) $input['region'] : null;
        $limit = min(max((int) ($input['limit'] ?? 8), 1), 15);

        $periodLabel = $from->format('d/m/Y').' a '.$to->format('d/m/Y');

        return match ($view) {
            'profitability' => $this->profitabilityView($from, $to, $limit, $region, $periodLabel),
            'investment' => $this->investmentView($from, $to, $limit, $region, $periodLabel),
            'divestment' => $this->divestmentView($from, $to, $region, $periodLabel),
            default => $this->occupancyView($from, $to, $groupBy, $region, $limit, $periodLabel),
        };
    }

    private function occupancyView(
        Carbon $from,
        Carbon $to,
        string $groupBy,
        ?string $region,
        int $limit,
        string $periodLabel,
    ): AgentCommandResult {
        $summary = $this->fleetAnalytics->occupancySummary($from, $to, $region);
        $rows = $this->fleetAnalytics->occupancy($from, $to, $groupBy, $region)->take($limit);

        $message = "**Frota — ocupação** ({$periodLabel})\n\n"
            .'• Taxa geral: **'.number_format($summary['taxa_ocupacao'], 1, ',', '.')."%**\n"
            .'• Patrimônios: **'.$summary['patrimonios']."**\n"
            .'• Locações no período: **'.$summary['locacoes']."**\n"
            .'• Dias comprometidos: **'.$summary['dias_comprometidos'].'** / '
            .($summary['dias_periodo'] * max(1, $summary['patrimonios']));

        if ($rows->isNotEmpty()) {
            $message .= "\n\n**Por ".match ($groupBy) {
                'model' => 'modelo',
                'category' => 'categoria',
                default => 'patrimônio',
            }.":**";
            foreach ($rows as $row) {
                $message .= "\n• {$row->grupo_nome}: **".number_format($row->taxa_ocupacao, 1, ',', '.').'%** ('.$row->locacoes.' loc.)';
            }
        }

        return $this->success(
            $message,
            [
                'entity' => 'fleet_analytics',
                'view' => 'occupancy',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'region' => $region,
                'summary' => $summary,
                'rows' => $rows->map(fn ($r) => (array) $r)->values()->all(),
            ],
            [
                ['label' => 'Abrir indicadores de frota', 'url' => CopilotNavigationLinks::fleetAnalytics($from->toDateString(), $to->toDateString(), 'ocupacao'), 'primary' => true],
            ],
        );
    }

    private function profitabilityView(
        Carbon $from,
        Carbon $to,
        int $limit,
        ?string $region,
        string $periodLabel,
    ): AgentCommandResult {
        $rows = $this->fleetAnalytics->profitabilityByAsset($from, $to, $limit, $region);

        $totalFat = $rows->sum('faturamento');
        $totalRes = $rows->sum('resultado_operacional');

        $message = "**Frota — rentabilidade** ({$periodLabel})\n\n"
            .'• Faturamento (top '.$rows->count().'): **R$ '.number_format($totalFat, 2, ',', '.')."**\n"
            .'• Resultado operacional: **R$ '.number_format($totalRes, 2, ',', '.').'**';

        foreach ($rows->take(5) as $row) {
            $message .= "\n• {$row->grupo_nome}: R$ ".number_format($row->faturamento, 2, ',', '.')
                .' (res. R$ '.number_format($row->resultado_operacional, 2, ',', '.').')';
        }

        return $this->success(
            $message,
            [
                'entity' => 'fleet_analytics',
                'view' => 'profitability',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'rows' => $rows->map(fn ($r) => (array) $r)->values()->all(),
            ],
            [
                ['label' => 'Análise de frota', 'url' => CopilotNavigationLinks::fleetAnalytics($from->toDateString(), $to->toDateString(), 'rentabilidade'), 'primary' => true],
            ],
        );
    }

    private function investmentView(
        Carbon $from,
        Carbon $to,
        int $limit,
        ?string $region,
        string $periodLabel,
    ): AgentCommandResult {
        $rows = $this->fleetAnalytics->investmentAnalysis($from, $to, $limit, $region);

        $message = "**Frota — ROI / payback** ({$periodLabel})\n\n";

        foreach ($rows->take(5) as $row) {
            $payback = $row->payback_meses !== null ? $row->payback_meses.' meses' : '—';
            $roi = $row->roi_vida_util_percent !== null
                ? number_format($row->roi_vida_util_percent, 1, ',', '.').'%'
                : '—';
            $message .= "• {$row->grupo_nome}: ocup. ".number_format($row->taxa_ocupacao, 1, ',', '.')
                ."%, payback {$payback}, ROI vida útil {$roi}\n";
        }

        if ($rows->isEmpty()) {
            $message .= '_Sem patrimônios com movimento no período._';
        }

        return $this->success(
            $message,
            [
                'entity' => 'fleet_analytics',
                'view' => 'investment',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'rows' => $rows->map(fn ($r) => (array) $r)->values()->all(),
            ],
            [
                ['label' => 'ROI e payback', 'url' => CopilotNavigationLinks::fleetAnalytics($from->toDateString(), $to->toDateString(), 'investimento'), 'primary' => true],
            ],
        );
    }

    private function divestmentView(
        Carbon $from,
        Carbon $to,
        ?string $region,
        string $periodLabel,
    ): AgentCommandResult {
        $rows = $this->fleetAnalytics->divestmentSuggestions($from, $to, $region);

        $message = "**Frota — desinvestimento** ({$periodLabel})\n\n";

        if ($rows->isEmpty()) {
            $message .= 'Nenhum patrimônio com sinal forte de desinvestimento no período.';
        } else {
            $message .= '**'. $rows->count().' sugestão(ões):**';
            foreach ($rows->take(8) as $row) {
                $reason = Str::limit((string) $row->divestir_motivo, 80);
                $message .= "\n• {$row->grupo_nome} — {$reason}";
            }
        }

        return $this->success(
            $message,
            [
                'entity' => 'fleet_analytics',
                'view' => 'divestment',
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'rows' => $rows->map(fn ($r) => (array) $r)->values()->all(),
            ],
            [
                ['label' => 'Desinvestimento', 'url' => CopilotNavigationLinks::fleetAnalytics($from->toDateString(), $to->toDateString(), 'desinvestimento'), 'primary' => true],
            ],
        );
    }
}
