<?php

namespace App\Services;

use App\Models\Domain\Maintenance\PartCatalogItem;
use Illuminate\Support\Collection;

class PartCatalogService
{
    /** @return Collection<int, PartCatalogItem> */
    public function search(string $term, int $limit = 8): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        return PartCatalogItem::query()
            ->active()
            ->where(function ($query) use ($term) {
                $query->where('codigo_peca', 'like', '%'.$term.'%')
                    ->orWhere('codigo_alternativo', 'like', '%'.$term.'%')
                    ->orWhere('descricao', 'like', '%'.$term.'%');
            })
            ->orderByRaw('CASE WHEN codigo_peca = ? THEN 0 WHEN codigo_peca LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])
            ->orderBy('descricao')
            ->limit($limit)
            ->get();
    }

    public function findByCode(string $code): ?PartCatalogItem
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return PartCatalogItem::query()
            ->active()
            ->where(function ($query) use ($code) {
                $query->where('codigo_peca', $code)
                    ->orWhere('codigo_alternativo', $code);
            })
            ->first();
    }
}
