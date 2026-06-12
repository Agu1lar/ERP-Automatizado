<?php

namespace App\Models\Domain\Maintenance;

use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartCatalogSupplierPrice extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'part_catalog_item_id',
        'company_id',
        'valor_unitario',
        'part_purchase_order_id',
        'user_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(PartCatalogItem::class, 'part_catalog_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PartPurchaseOrder::class, 'part_purchase_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
