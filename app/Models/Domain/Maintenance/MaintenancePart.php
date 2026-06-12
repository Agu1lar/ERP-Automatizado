<?php

namespace App\Models\Domain\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePart extends Model
{
    protected $fillable = [
        'maintenance_order_id',
        'part_catalog_item_id',
        'descricao',
        'codigo_peca',
        'codigo_alternativo',
        'quantidade',
        'valor_unitario',
        'observacao',
        'estoque_baixado',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'valor_unitario' => 'decimal:2',
            'estoque_baixado' => 'boolean',
        ];
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(PartCatalogItem::class, 'part_catalog_item_id');
    }

    public function subtotal(): float
    {
        return (float) (($this->valor_unitario ?? 0) * $this->quantidade);
    }
}
