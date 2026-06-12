<?php

namespace App\Services;

use App\Models\Domain\Fleet\Asset;
use Carbon\CarbonInterface;

class AssetDepreciationService
{
    public function bookValue(Asset $asset, ?CarbonInterface $asOf = null): ?float
    {
        $purchaseValue = $asset->valor_compra !== null ? (float) $asset->valor_compra : null;

        if ($purchaseValue === null || $purchaseValue <= 0) {
            return null;
        }

        $purchaseDate = $asset->data_compra?->copy()->startOfDay();

        if ($purchaseDate === null) {
            return round($purchaseValue, 2);
        }

        $asOf ??= now();
        $usefulLifeYears = max(1, (int) config('fleet.depreciation.useful_life_years', 10));
        $residualPercent = min(100, max(0, (float) config('fleet.depreciation.residual_percent', 10)));
        $residualValue = $purchaseValue * ($residualPercent / 100);
        $depreciableBase = max(0, $purchaseValue - $residualValue);

        $yearsOwned = $purchaseDate->floatDiffInYears($asOf->copy()->startOfDay());

        if ($yearsOwned <= 0) {
            return round($purchaseValue, 2);
        }

        if ($yearsOwned >= $usefulLifeYears) {
            return round($residualValue, 2);
        }

        $depreciated = $depreciableBase * ($yearsOwned / $usefulLifeYears);

        return round(max($residualValue, $purchaseValue - $depreciated), 2);
    }

    public function accumulatedDepreciation(Asset $asset, ?CarbonInterface $asOf = null): ?float
    {
        $purchaseValue = $asset->valor_compra !== null ? (float) $asset->valor_compra : null;
        $book = $this->bookValue($asset, $asOf);

        if ($purchaseValue === null || $book === null) {
            return null;
        }

        return round(max(0, $purchaseValue - $book), 2);
    }
}
