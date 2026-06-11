<?php

namespace App\Support;

use App\Models\Domain\Finance\ReceivableTitle;
use App\Services\LateFeeChargeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DelinquencyReportQuery
{
    /**
     * @return Collection<int, object{
     *     customer_id: int,
     *     customer_nome: string,
     *     total_aberto: float,
     *     total_atrasado: float,
     *     ate_30: float,
     *     ate_60: float,
     *     ate_90: float,
     *     acima_90: float,
     *     titulos_atrasados: int
     * }>
     */
    public function customersWithAging(?string $search = null): Collection
    {
        $today = now()->toDateString();
        $d30 = now()->subDays(30)->toDateString();
        $d60 = now()->subDays(60)->toDateString();
        $d90 = now()->subDays(90)->toDateString();

        $query = ReceivableTitle::query()
            ->open()
            ->join('customers', 'customers.id', '=', 'receivable_titles.customer_id')
            ->when($search, function ($inner) use ($search) {
                $term = '%'.$search.'%';
                $inner->where('customers.nome', 'like', $term);
            })
            ->select([
                'receivable_titles.customer_id',
                'customers.nome as customer_nome',
                DB::raw('SUM(receivable_titles.valor) as total_aberto'),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$today}' THEN receivable_titles.valor ELSE 0 END) as total_atrasado"),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$today}' AND receivable_titles.vencimento >= '{$d30}' THEN receivable_titles.valor ELSE 0 END) as ate_30"),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$d30}' AND receivable_titles.vencimento >= '{$d60}' THEN receivable_titles.valor ELSE 0 END) as ate_60"),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$d60}' AND receivable_titles.vencimento >= '{$d90}' THEN receivable_titles.valor ELSE 0 END) as ate_90"),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$d90}' THEN receivable_titles.valor ELSE 0 END) as acima_90"),
                DB::raw("SUM(CASE WHEN receivable_titles.vencimento < '{$today}' THEN 1 ELSE 0 END) as titulos_atrasados"),
            ])
            ->groupBy('receivable_titles.customer_id', 'customers.nome')
            ->havingRaw('SUM(CASE WHEN receivable_titles.vencimento < ? THEN receivable_titles.valor ELSE 0 END) > 0', [$today])
            ->orderByDesc('total_atrasado');

        return $query->get();
    }

    /** @return array{total_aberto: float, total_atrasado: float, ate_30: float, ate_60: float, ate_90: float, acima_90: float, clientes: int} */
    public function summary(): array
    {
        $today = now()->toDateString();
        $d30 = now()->subDays(30)->toDateString();
        $d60 = now()->subDays(60)->toDateString();
        $d90 = now()->subDays(90)->toDateString();

        $totals = ReceivableTitle::query()
            ->open()
            ->selectRaw('SUM(valor) as total_aberto')
            ->selectRaw('SUM(CASE WHEN vencimento < ? THEN valor ELSE 0 END) as total_atrasado', [$today])
            ->selectRaw('SUM(CASE WHEN vencimento < ? AND vencimento >= ? THEN valor ELSE 0 END) as ate_30', [$today, $d30])
            ->selectRaw('SUM(CASE WHEN vencimento < ? AND vencimento >= ? THEN valor ELSE 0 END) as ate_60', [$d30, $d60])
            ->selectRaw('SUM(CASE WHEN vencimento < ? AND vencimento >= ? THEN valor ELSE 0 END) as ate_90', [$d60, $d90])
            ->selectRaw('SUM(CASE WHEN vencimento < ? THEN valor ELSE 0 END) as acima_90', [$d90])
            ->first();

        $clientes = ReceivableTitle::query()
            ->overdue()
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'total_aberto' => (float) ($totals->total_aberto ?? 0),
            'total_atrasado' => (float) ($totals->total_atrasado ?? 0),
            'ate_30' => (float) ($totals->ate_30 ?? 0),
            'ate_60' => (float) ($totals->ate_60 ?? 0),
            'ate_90' => (float) ($totals->ate_90 ?? 0),
            'acima_90' => (float) ($totals->acima_90 ?? 0),
            'clientes' => $clientes,
        ];
    }

    public function overdueTitlesCount(): int
    {
        return ReceivableTitle::query()->overdue()->count();
    }

    /**
     * @return Collection<int, object{
     *     title: ReceivableTitle,
     *     valor_limpo: float,
     *     multa_percent: float,
     *     juros_mensal_percent: float,
     *     multa_valor: float,
     *     juros_valor: float,
     *     valor_total: float,
     *     dias_atraso: int,
     *     rule_source: string,
     *     is_applied: bool
     * }>
     */
    public function overdueTitlesWithCharges(?string $search = null): Collection
    {
        $service = app(LateFeeChargeService::class);

        return ReceivableTitle::query()
            ->overdue()
            ->with(['customer', 'rental'])
            ->when($search, function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->whereHas('customer', fn ($customer) => $customer->where('nome', 'like', $term));
            })
            ->orderBy('vencimento')
            ->get()
            ->map(function (ReceivableTitle $title) use ($service) {
                $breakdown = $service->breakdownForTitle($title);

                return (object) array_merge(['title' => $title], $breakdown);
            });
    }

    /** @return array{valor_limpo: float, multa_valor: float, juros_valor: float, valor_total: float} */
    public function chargeSummary(?string $search = null): array
    {
        $details = $this->overdueTitlesWithCharges($search);

        return [
            'valor_limpo' => (float) $details->sum('valor_limpo'),
            'multa_valor' => (float) $details->sum('multa_valor'),
            'juros_valor' => (float) $details->sum('juros_valor'),
            'valor_total' => (float) $details->sum('valor_total'),
        ];
    }
}
