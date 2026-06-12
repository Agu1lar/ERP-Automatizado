<?php

namespace App\Support;

use App\Models\Domain\Customer\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InactiveCustomerQuery
{
    public function baseQuery(int $monthsInactive = 6): Builder
    {
        $cutoff = Carbon::now()->subMonths(max(1, $monthsInactive))->startOfDay();

        return Customer::query()
            ->where('ativo', true)
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->whereDoesntHave('rentals', function (Builder $query) use ($cutoff) {
                $query->where(function (Builder $inner) use ($cutoff) {
                    $inner->where('reserved_at', '>=', $cutoff)
                        ->orWhere('checkout_at', '>=', $cutoff)
                        ->orWhere('returned_at', '>=', $cutoff);
                });
            });
    }

    public function count(int $monthsInactive = 6): int
    {
        return $this->baseQuery($monthsInactive)->count();
    }
}
