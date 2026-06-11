<?php

namespace App\Services;

use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use Illuminate\Support\Collection;

class AccountingExportService
{
    /** @param  Collection<int, ReceivableTitle>  $titles */
    public function markExportedToErp(Collection $titles, string $format, ?User $user = null): int
    {
        $user ??= auth()->user();
        $ids = $titles->pluck('id')->filter()->all();

        if ($ids === []) {
            return 0;
        }

        return ReceivableTitle::query()
            ->whereIn('id', $ids)
            ->whereNull('exportado_erp_em')
            ->update([
                'exportado_erp_em' => now(),
                'exportado_erp_por' => $user?->id,
                'exportado_erp_formato' => $format,
            ]);
    }

    public function markSingleExported(ReceivableTitle $title, string $format, ?User $user = null): ReceivableTitle
    {
        $user ??= auth()->user();

        $title->update([
            'exportado_erp_em' => now(),
            'exportado_erp_por' => $user?->id,
            'exportado_erp_formato' => $format,
        ]);

        return $title->fresh(['customer', 'rental', 'exportadoErpByUser']);
    }

    public function clearExportedFlag(ReceivableTitle $title): ReceivableTitle
    {
        $title->update([
            'exportado_erp_em' => null,
            'exportado_erp_por' => null,
            'exportado_erp_formato' => null,
        ]);

        return $title->fresh();
    }
}
