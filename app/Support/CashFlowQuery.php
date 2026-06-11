<?php

namespace App\Support;

use App\Models\Domain\Finance\ReceivableTitle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowQuery
{
    /**
     * @return Collection<int, object{periodo: string, vencimento: string, quantidade: int, valor: float}>
     */
    public function expectedInflows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return ReceivableTitle::query()
            ->open()
            ->whereBetween('vencimento', [$from->toDateString(), $to->toDateString()])
            ->select([
                'vencimento',
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(valor) as valor'),
            ])
            ->groupBy('vencimento')
            ->orderBy('vencimento')
            ->get()
            ->map(fn ($row) => (object) [
                'periodo' => $row->vencimento,
                'vencimento' => $row->vencimento,
                'quantidade' => (int) $row->quantidade,
                'valor' => (float) $row->valor,
            ]);
    }

    public function totalExpected(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) ReceivableTitle::query()
            ->open()
            ->whereBetween('vencimento', [$from->toDateString(), $to->toDateString()])
            ->sum('valor');
    }
}
