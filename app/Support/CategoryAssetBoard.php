<?php

namespace App\Support;

use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use Illuminate\Support\Collection;

class CategoryAssetBoard
{
    public const GROUP_DISPONIVEL = 'disponivel';

    public const GROUP_LOCADO = 'locado';

    public const GROUP_MANUTENCAO = 'manutencao';

    /** @return array<string, string> */
    public static function groupLabels(): array
    {
        return [
            self::GROUP_DISPONIVEL => 'Disponíveis',
            self::GROUP_LOCADO => 'Locados',
            self::GROUP_MANUTENCAO => 'Em manutenção',
        ];
    }

    /** @return Collection<string, Collection<int, Asset>> */
    public static function forCategory(EquipmentCategory $category): Collection
    {
        $assets = Asset::query()
            ->with('equipmentModel')
            ->whereHas('equipmentModel', fn ($query) => $query->where('equipment_category_id', $category->id))
            ->orderBy('codigo_patrimonio')
            ->get();

        $grouped = collect(self::groupLabels())->mapWithKeys(
            fn (string $label, string $key) => [$key => collect()]
        );

        foreach ($assets as $asset) {
            $group = self::resolveGroup($asset->statusEnum());

            if ($grouped->has($group)) {
                $grouped[$group]->push($asset);
            }
        }

        return $grouped;
    }

    public static function resolveGroup(AssetStatus $status): string
    {
        return match ($status) {
            AssetStatus::Disponivel,
            AssetStatus::Reservado => self::GROUP_DISPONIVEL,
            AssetStatus::Locado,
            AssetStatus::EmInspecao,
            AssetStatus::EmManutencaoCampo => self::GROUP_LOCADO,
            AssetStatus::EmManutencao,
            AssetStatus::AguardandoPeca => self::GROUP_MANUTENCAO,
            default => '',
        };
    }
}
