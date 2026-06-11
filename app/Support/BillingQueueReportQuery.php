<?php

namespace App\Support;

use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use Illuminate\Support\Collection;

class BillingQueueReportQuery
{
    /** @return array{total_nf: float, total_car: float, total_registros: int, grupos: Collection} */
    public function summary(?string $status = null): array
    {
        $query = RentalBillingQueueEntry::query()
            ->with(['customer', 'rental'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('gerado_em');

        $entries = $query->get();

        $grupos = $entries
            ->groupBy('tipo')
            ->map(function (Collection $group, string $tipo) {
                $type = RentalBillingQueueType::from($tipo);

                return [
                    'tipo' => $type,
                    'label' => $type->label(),
                    'registros' => $group->count(),
                    'total_nf' => round((float) $group->sum('valor_nf'), 2),
                    'total_car' => round((float) $group->sum('valor_car'), 2),
                    'entries' => $group,
                ];
            })
            ->values();

        return [
            'total_nf' => round((float) $entries->sum('valor_nf'), 2),
            'total_car' => round((float) $entries->sum('valor_car'), 2),
            'total_registros' => $entries->count(),
            'grupos' => $grupos,
        ];
    }

    public function pendingCount(): int
    {
        return RentalBillingQueueEntry::query()->pendingInvoice()->count();
    }
}
