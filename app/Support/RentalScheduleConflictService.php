<?php

namespace App\Support;

use App\Enums\RentalStatus;
use App\Models\Domain\Rental\Rental;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RentalScheduleConflictService
{
    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public function occupancyRange(Rental $rental): ?array
    {
        if ($rental->statusEnum() === RentalStatus::Cancelado) {
            return null;
        }

        $start = $rental->scheduleStart();

        if ($start === null) {
            return null;
        }

        $end = match ($rental->statusEnum()) {
            RentalStatus::Concluido => $rental->completed_at?->copy()->startOfDay()
                ?? $rental->returned_at?->copy()->startOfDay(),
            default => $rental->expected_return_at?->copy()->startOfDay()
                ?? $rental->returned_at?->copy()->startOfDay(),
        };

        if ($end === null || $end->lt($start)) {
            return null;
        }

        return [$start->copy()->startOfDay(), $end->copy()->startOfDay()];
    }

    public function analyze(
        int $assetId,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        ?int $excludeRentalId = null,
    ): RentalScheduleConflictResult {
        $start = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->startOfDay();

        if ($end->lt($start)) {
            return RentalScheduleConflictResult::conflict(
                'A previsão de retorno deve ser igual ou posterior ao início da locação.',
                collect(),
            );
        }

        $overlapping = $this->overlappingRentals($assetId, $start, $end, $excludeRentalId);

        if ($overlapping->isEmpty()) {
            return RentalScheduleConflictResult::none($start->gt(now()->startOfDay()));
        }

        $codes = $overlapping->pluck('codigo')->implode(', ');

        return RentalScheduleConflictResult::conflict(
            "Conflito de agenda com locação(ões): {$codes}. Ajuste as datas ou escolha outro patrimônio.",
            $overlapping,
            $start->gt(now()->startOfDay()),
        );
    }

    /** @return Collection<int, Rental> */
    public function overlappingRentals(
        int $assetId,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        ?int $excludeRentalId = null,
    ): Collection {
        $start = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->startOfDay();

        return Rental::query()
            ->where('asset_id', $assetId)
            ->whereNotIn('status', [
                RentalStatus::Cancelado->value,
                RentalStatus::Concluido->value,
            ])
            ->when($excludeRentalId, fn ($query) => $query->where('id', '!=', $excludeRentalId))
            ->get()
            ->filter(function (Rental $rental) use ($start, $end) {
                $range = $this->occupancyRange($rental);

                if ($range === null) {
                    return $rental->statusEnum()->isActive();
                }

                return $this->rangesOverlap($start, $end, $range[0], $range[1]);
            })
            ->values();
    }

    private function rangesOverlap(
        CarbonInterface $startA,
        CarbonInterface $endA,
        CarbonInterface $startB,
        CarbonInterface $endB,
    ): bool {
        return $startA->lte($endB) && $startB->lte($endA);
    }
}
