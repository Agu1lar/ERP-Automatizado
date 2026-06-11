<?php



namespace App\Support;



use App\Enums\RentalBillingQueueType;
use App\Models\Domain\Finance\ReceivableTitle;

use App\Models\Domain\Rental\Rental;

use App\Models\Domain\Rental\RentalBillingQueueEntry;

use Illuminate\Support\Collection;



class FinanceDashboardQuery

{

    /** @return array{total: float, quantidade: int, inicio: string, fim: string} */

    public function receivableThisWeekSummary(): array

    {

        $start = now()->startOfWeek()->toDateString();

        $end = now()->endOfWeek()->toDateString();



        $totals = ReceivableTitle::query()

            ->open()

            ->whereBetween('vencimento', [$start, $end])

            ->selectRaw('COUNT(*) as quantidade')

            ->selectRaw('COALESCE(SUM(valor), 0) as total')

            ->first();



        return [

            'total' => (float) ($totals->total ?? 0),

            'quantidade' => (int) ($totals->quantidade ?? 0),

            'inicio' => $start,

            'fim' => $end,

        ];

    }



    /** @return Collection<int, ReceivableTitle> */

    public function receivableThisWeekTitles(int $limit = 8): Collection

    {

        $start = now()->startOfWeek()->toDateString();

        $end = now()->endOfWeek()->toDateString();



        return ReceivableTitle::query()

            ->open()

            ->with(['customer', 'rental'])

            ->whereBetween('vencimento', [$start, $end])

            ->orderBy('vencimento')

            ->limit($limit)

            ->get();

    }



    public function billingCycleDueCount(): int

    {

        return Rental::query()->billingCycleDue()->count();

    }



    /** @return Collection<int, Rental> */

    public function billingCycleDueRentals(int $limit = 8): Collection

    {

        return Rental::query()

            ->billingCycleDue()

            ->with(['customer', 'asset.equipmentModel'])

            ->orderBy('next_billing_at')

            ->limit($limit)

            ->get();

    }



    public function pendingRenewalQueueCount(): int

    {

        return RentalBillingQueueEntry::query()

            ->where('tipo', RentalBillingQueueType::Renovacao->value)

            ->pendingInvoice()

            ->count();

    }



    public function rentalsWithDueCycleWithoutRenewalQueue(): int

    {

        return Rental::query()

            ->billingCycleDue()

            ->whereDoesntHave('billingQueueEntries', function ($query) {

                $query

                    ->where('tipo', RentalBillingQueueType::Renovacao->value)

                    ->pendingInvoice();

            })

            ->count();

    }

}


