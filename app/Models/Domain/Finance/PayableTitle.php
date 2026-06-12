<?php

namespace App\Models\Domain\Finance;

use App\Enums\PayableTitleOrigin;
use App\Enums\PayableTitleStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableTitle extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'company_id',
        'part_purchase_order_id',
        'maintenance_order_id',
        'origem',
        'valor',
        'vencimento',
        'status',
        'forma_pagamento',
        'pago_em',
        'pago_por',
        'observacoes',
        'observacoes_pagamento',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PartPurchaseOrder::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_por');
    }

    public function statusEnum(): PayableTitleStatus
    {
        return PayableTitleStatus::from($this->status);
    }

    public function originEnum(): PayableTitleOrigin
    {
        return PayableTitleOrigin::from($this->origem);
    }

    public function paymentMethodEnum(): ?PaymentMethod
    {
        return $this->forma_pagamento
            ? PaymentMethod::from($this->forma_pagamento)
            : null;
    }

    public function isOverdue(): bool
    {
        return $this->status === PayableTitleStatus::Aberto->value
            && $this->vencimento->lt(now()->startOfDay());
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->vencimento->diffInDays(now()->startOfDay());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PayableTitleStatus::Aberto->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->open()
            ->whereDate('vencimento', '<', now()->toDateString());
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PayableTitleStatus::Pago->value);
    }
}
