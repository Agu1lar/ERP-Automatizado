<?php

namespace App\Services;

use App\Enums\RentalPricingPeriod;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentPricing;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EquipmentPricingService
{
    /**
     * @return Collection<int, array{
     *     category: EquipmentCategory,
     *     prices: array<string, string>,
     *     model_override_count: int
     * }>
     */
    public function categoryGrid(): Collection
    {
        $categories = EquipmentCategory::query()->orderBy('nome')->get();

        $categoryPricings = EquipmentPricing::query()
            ->whereNotNull('equipment_category_id')
            ->whereNull('equipment_model_id')
            ->get()
            ->groupBy('equipment_category_id');

        $modelOverrideCounts = EquipmentPricing::query()
            ->whereNotNull('equipment_model_id')
            ->selectRaw('equipment_models.equipment_category_id as category_id, count(*) as total')
            ->join('equipment_models', 'equipment_models.id', '=', 'equipment_pricings.equipment_model_id')
            ->groupBy('equipment_models.equipment_category_id')
            ->pluck('total', 'category_id');

        return $categories->map(function (EquipmentCategory $category) use ($categoryPricings, $modelOverrideCounts) {
            $prices = [];

            foreach (RentalPricingPeriod::cases() as $period) {
                $pricing = $categoryPricings
                    ->get($category->id, collect())
                    ->first(fn (EquipmentPricing $row) => $row->periodo === $period->value && $row->ativo);

                $prices[$period->value] = $pricing !== null
                    ? number_format((float) $pricing->valor, 2, '.', '')
                    : '';
            }

            return [
                'category' => $category,
                'prices' => $prices,
                'model_override_count' => (int) ($modelOverrideCounts[$category->id] ?? 0),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $pricesByPeriod
     */
    public function syncCategoryPrices(int $categoryId, array $pricesByPeriod): void
    {
        EquipmentCategory::query()->findOrFail($categoryId);

        foreach (RentalPricingPeriod::cases() as $period) {
            $raw = $pricesByPeriod[$period->value] ?? '';
            $normalized = trim((string) $raw);

            $existing = EquipmentPricing::query()
                ->where('equipment_category_id', $categoryId)
                ->whereNull('equipment_model_id')
                ->where('periodo', $period->value)
                ->first();

            if ($normalized === '') {
                $existing?->delete();

                continue;
            }

            if (! is_numeric(str_replace(',', '.', $normalized))) {
                throw new InvalidArgumentException("Valor inválido para {$period->label()}.");
            }

            $valor = (float) str_replace(',', '.', $normalized);

            if ($valor < 0) {
                throw new InvalidArgumentException("Valor de {$period->label()} não pode ser negativo.");
            }

            EquipmentPricing::updateOrCreate(
                [
                    'equipment_category_id' => $categoryId,
                    'equipment_model_id' => null,
                    'periodo' => $period->value,
                ],
                [
                    'valor' => round($valor, 2),
                    'ativo' => true,
                ],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $grid
     */
    public function syncAllCategoryPrices(array $grid): void
    {
        foreach ($grid as $categoryId => $pricesByPeriod) {
            $this->syncCategoryPrices((int) $categoryId, $pricesByPeriod);
        }
    }
}
