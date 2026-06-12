<?php

namespace App\Models\Domain\Maintenance;

use App\Enums\PartStockMovementType;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartStockMovement extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'part_catalog_item_id',
        'maintenance_order_id',
        'maintenance_part_id',
        'tipo',
        'quantidade',
        'saldo_anterior',
        'saldo_posterior',
        'user_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
        ];
    }

    public function tipoEnum(): PartStockMovementType
    {
        return PartStockMovementType::from($this->tipo);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(PartCatalogItem::class, 'part_catalog_item_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function maintenancePart(): BelongsTo
    {
        return $this->belongsTo(MaintenancePart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
