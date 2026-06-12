<?php

namespace App\Services;

use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartCatalogSupplierPrice;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Illuminate\Support\Collection;

class PartSupplierPriceService
{
    public function record(
        PartCatalogItem $item,
        Company $supplier,
        float $valorUnitario,
        ?PartPurchaseOrder $purchaseOrder = null,
        ?string $observacao = null,
        ?User $user = null,
    ): PartCatalogSupplierPrice {
        $user ??= auth()->user();

        return PartCatalogSupplierPrice::create([
            'operating_company_id' => $item->operating_company_id,
            'part_catalog_item_id' => $item->id,
            'company_id' => $supplier->id,
            'valor_unitario' => round($valorUnitario, 2),
            'part_purchase_order_id' => $purchaseOrder?->id,
            'user_id' => $user?->id,
            'observacao' => $observacao,
        ]);
    }

    /** @return Collection<int, PartCatalogSupplierPrice> */
    public function historyForPart(PartCatalogItem $item, int $limit = 20): Collection
    {
        return $item->supplierPrices()
            ->with('supplier')
            ->limit($limit)
            ->get();
    }

    public function latestForSupplier(PartCatalogItem $item, Company $supplier): ?PartCatalogSupplierPrice
    {
        return PartCatalogSupplierPrice::query()
            ->where('part_catalog_item_id', $item->id)
            ->where('company_id', $supplier->id)
            ->latest()
            ->first();
    }
}
