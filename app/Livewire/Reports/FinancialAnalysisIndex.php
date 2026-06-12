<?php

namespace App\Livewire\Reports;

use App\Services\ProfitabilityReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FinancialAnalysisIndex extends Component
{
    use AuthorizesRequests;

    public string $date_from = '';

    public string $date_to = '';

    public string $view_mode = 'geral';

    public string $region_filter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.analytics'), 403);
        $this->date_from = now()->subDays(90)->toDateString();
        $this->date_to = now()->toDateString();
    }

    public function render(): View
    {
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->endOfDay();
        $service = app(ProfitabilityReportService::class);
        $region = $this->region_filter !== '' ? $this->region_filter : null;

        $summary = $service->summary($from, $to, $region);
        $rows = match ($this->view_mode) {
            'category' => $service->byCategory($from, $to, $region),
            'asset' => $service->byAsset($from, $to, 100, $region),
            default => collect([(object) [
                'grupo_nome' => 'Total no período',
                'locacoes' => $summary['locacoes'],
                'os_concluidas' => $summary['os_concluidas'],
                'faturamento' => $summary['faturamento'],
                'custo_pecas' => $summary['custo_pecas'],
                'custo_mao_obra' => $summary['custo_mao_obra'],
                'custo_manutencao' => $summary['custo_manutencao'],
                'resultado' => $summary['resultado'],
            ]]),
        };

        return view('livewire.reports.financial-analysis-index', [
            'summary' => $summary,
            'rows' => $rows,
            'regionOptions' => \App\Enums\GeographicRegion::cases(),
        ]);
    }
}
