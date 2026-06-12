<?php

namespace App\Models\Domain\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartPurchaseOrderItem extends Model
{
    protected $fillable = [
        'part_purchase_order_id',
        'part_catalog_item_id',
        'quantidade_pedida',
        'quantidade_recebida',
        'valor_unitario',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_pedida' => 'decimal:2',
            'quantidade_recebida' => 'decimal:2',
            'valor_unitario' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PartPurchaseOrder::class, 'part_purchase_order_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(PartCatalogItem::class, 'part_catalog_item_id');
    }

    public function pendingQuantity(): float
    {
        return max(0, (float) $this->quantidade_pedida - (float) $this->quantidade_recebida);
    }
}
