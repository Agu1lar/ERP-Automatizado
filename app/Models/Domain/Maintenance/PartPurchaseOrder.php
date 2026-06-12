<?php

namespace App\Models\Domain\Maintenance;

use App\Enums\PartPurchaseOrderStatus;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartPurchaseOrder extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'company_id',
        'status',
        'pedido_em',
        'recebido_em',
        'observacoes',
        'created_by',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'pedido_em' => 'date',
            'recebido_em' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartPurchaseOrderItem::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function statusEnum(): PartPurchaseOrderStatus
    {
        return PartPurchaseOrderStatus::from($this->status);
    }

    public function payableTitle(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Domain\Finance\PayableTitle::class);
    }

    public function totalValue(): float
    {
        $this->loadMissing('items');

        return round($this->items->sum(function (PartPurchaseOrderItem $item) {
            $unit = (float) ($item->valor_unitario ?? 0);

            return (float) $item->quantidade_pedida * $unit;
        }), 2);
    }
}
