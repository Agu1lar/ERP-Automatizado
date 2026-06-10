<?php

namespace App\Support;

use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;

class MaintenanceOsBuilder
{
    /** @return array<string, mixed> */
    public static function assetPreview(Asset $asset): array
    {
        $asset->loadMissing(['equipmentModel.category']);

        $rental = $asset->activeRental();
        $rental?->loadMissing('customer');
        $recentParts = MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->with('parts')
            ->latest('opened_at')
            ->limit(3)
            ->get()
            ->pluck('parts')
            ->flatten()
            ->unique('descricao')
            ->take(5)
            ->map(fn ($part) => $part->descricao)
            ->values()
            ->all();

        return [
            'id' => $asset->id,
            'codigo' => $asset->codigo_patrimonio,
            'modelo' => $asset->equipmentModel->displayName(),
            'marca' => $asset->equipmentModel->marca,
            'categoria' => $asset->equipmentModel->category->nome,
            'serie' => $asset->serie,
            'voltagem' => $asset->voltagem,
            'horimetro' => $asset->horimetro,
            'status' => $asset->statusEnum()->label(),
            'has_open_os' => $asset->activeMaintenanceOrder() !== null,
            'cliente' => $rental?->customer?->nome,
            'locacao' => $rental?->codigo,
            'recent_parts' => $recentParts,
        ];
    }
}
