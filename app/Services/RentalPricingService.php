<?php

namespace App\Services;

use App\Enums\RentalPricingPeriod;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Models\Domain\Rental\Rental;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RentalPricingService
{
    /**
     * @return array{
     *     period: RentalPricingPeriod,
     *     unit_price: float,
     *     billed_days: int,
     *     billed_units: int,
     *     valor_calculado: float,
     *     breakdown: string,
     *     source: string
     * }|null
     */
    public function calculate(
        Asset $asset,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?RentalPricingPeriod $period = null,
    ): ?array {
        $billedDays = $this->billingDays($startDate, $endDate);

        if ($billedDays < 1) {
            return null;
        }

        $period ??= $this->suggestBestPeriod($asset, $billedDays);

        if (! $period) {
            return null;
        }

        $unitPrice = $this->resolveUnitPrice($asset, $period);

        if ($unitPrice === null) {
            return null;
        }

        $billedUnits = $this->billedUnits($period, $billedDays);
        $total = round($unitPrice * $billedUnits, 2);
        $source = $this->priceSource($asset, $period);

        return [
            'period' => $period,
            'unit_price' => $unitPrice,
            'billed_days' => $billedDays,
            'billed_units' => $billedUnits,
            'valor_calculado' => $total,
            'breakdown' => sprintf(
                '%d %s × R$ %s (%s)',
                $billedUnits,
                $period->unitLabel().($billedUnits > 1 ? 's' : ''),
                number_format($unitPrice, 2, ',', '.'),
                $period->label(),
            ),
            'source' => $source,
        ];
    }

    public function calculateForRental(Rental $rental, ?RentalPricingPeriod $period = null): ?array
    {
        $rental->loadMissing('asset.equipmentModel.category');

        $start = $rental->checkout_at ?? $rental->reserved_at ?? now();
        $end = $rental->expected_return_at;

        if (! $end) {
            return null;
        }

        $period ??= $rental->pricing_period
            ? RentalPricingPeriod::tryFrom($rental->pricing_period)
            : null;

        return $this->calculate($rental->asset, $start, $end, $period);
    }

    public function applyToRental(Rental $rental, ?RentalPricingPeriod $period = null, bool $overwriteFaturamento = true): ?array
    {
        $result = $this->calculateForRental($rental, $period);

        if (! $result) {
            return null;
        }

        $updates = [
            'pricing_period' => $result['period']->value,
            'billed_days' => $result['billed_days'],
            'valor_calculado' => $result['valor_calculado'],
        ];

        if ($overwriteFaturamento || $rental->valor_faturamento === null) {
            $updates['valor_faturamento'] = $result['valor_calculado'];
        }

        $rental->update($updates);

        return $result;
    }

    public function billingDays(CarbonInterface $startDate, CarbonInterface $endDate): int
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    public function billedUnits(RentalPricingPeriod $period, int $days): int
    {
        return match ($period) {
            RentalPricingPeriod::Diaria => max(1, $days),
            RentalPricingPeriod::Semanal => (int) max(1, ceil($days / 7)),
            RentalPricingPeriod::Mensal => (int) max(1, ceil($days / 30)),
        };
    }

    public function resolveUnitPrice(Asset $asset, RentalPricingPeriod $period): ?float
    {
        $asset->loadMissing('equipmentModel.category');

        $modelPrice = EquipmentPricing::query()
            ->active()
            ->where('equipment_model_id', $asset->equipment_model_id)
            ->where('periodo', $period->value)
            ->value('valor');

        if ($modelPrice !== null) {
            return (float) $modelPrice;
        }

        $categoryPrice = EquipmentPricing::query()
            ->active()
            ->where('equipment_category_id', $asset->equipmentModel->equipment_category_id)
            ->where('periodo', $period->value)
            ->value('valor');

        return $categoryPrice !== null ? (float) $categoryPrice : null;
    }

    public function suggestBestPeriod(Asset $asset, int $days): ?RentalPricingPeriod
    {
        $best = null;
        $bestTotal = null;

        foreach ($this->availablePeriodsForAsset($asset) as $period) {
            $unitPrice = $this->resolveUnitPrice($asset, $period);

            if ($unitPrice === null) {
                continue;
            }

            $total = $unitPrice * $this->billedUnits($period, $days);

            if ($bestTotal === null || $total < $bestTotal) {
                $bestTotal = $total;
                $best = $period;
            }
        }

        return $best;
    }

    /** @return Collection<int, RentalPricingPeriod> */
    public function availablePeriodsForAsset(Asset $asset): Collection
    {
        $asset->loadMissing('equipmentModel');

        $periods = EquipmentPricing::query()
            ->active()
            ->where(function ($query) use ($asset) {
                $query->where('equipment_model_id', $asset->equipment_model_id)
                    ->orWhere('equipment_category_id', $asset->equipmentModel->equipment_category_id);
            })
            ->pluck('periodo')
            ->unique()
            ->map(fn (string $period) => RentalPricingPeriod::from($period));

        return $periods->values();
    }

    private function priceSource(Asset $asset, RentalPricingPeriod $period): string
    {
        $asset->loadMissing('equipmentModel.category');

        $hasModel = EquipmentPricing::query()
            ->active()
            ->where('equipment_model_id', $asset->equipment_model_id)
            ->where('periodo', $period->value)
            ->exists();

        return $hasModel
            ? 'Tabela do modelo'
            : 'Tabela da categoria';
    }
}
