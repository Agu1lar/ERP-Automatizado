<?php

namespace App\Models\Domain\Finance;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Support\OperatingCompanyRelations;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Rental\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableTitle extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'customer_id',
        'rental_id',
        'maintenance_order_id',
        'parcela',
        'total_parcelas',
        'valor',
        'vencimento',
        'status',
        'forma_pagamento',
        'pago_em',
        'pago_por',
        'observacoes',
        'observacoes_pagamento',
        'multa_percent_aplicada',
        'juros_mensal_percent_aplicada',
        'multa_valor',
        'juros_valor',
        'valor_total_com_encargos',
        'encargos_aplicados_em',
        'encargos_aplicados_por',
        'exportado_erp_em',
        'exportado_erp_por',
        'exportado_erp_formato',
        'gateway_driver',
        'gateway_charge_id',
        'gateway_status',
        'gateway_billing_type',
        'pix_qr_code',
        'pix_qr_image_url',
        'boleto_url',
        'gateway_invoice_url',
        'gateway_charge_created_at',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'multa_percent_aplicada' => 'decimal:4',
            'juros_mensal_percent_aplicada' => 'decimal:4',
            'multa_valor' => 'decimal:2',
            'juros_valor' => 'decimal:2',
            'valor_total_com_encargos' => 'decimal:2',
            'encargos_aplicados_em' => 'datetime',
            'exportado_erp_em' => 'datetime',
            'gateway_charge_created_at' => 'datetime',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
            'parcela' => 'integer',
            'total_parcelas' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rental(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, Rental::class, 'rental');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Domain\Maintenance\MaintenanceOrder::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_por');
    }

    public function exportadoErpByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exportado_erp_por');
    }

    public function isExportedToErp(): bool
    {
        return $this->exportado_erp_em !== null;
    }

    public function statusEnum(): ReceivableTitleStatus
    {
        return ReceivableTitleStatus::from($this->status);
    }

    public function paymentMethodEnum(): ?PaymentMethod
    {
        return $this->forma_pagamento
            ? PaymentMethod::from($this->forma_pagamento)
            : null;
    }

    public function isOverdue(): bool
    {
        return $this->status === ReceivableTitleStatus::Aberto->value
            && $this->vencimento->lt(now()->startOfDay());
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->vencimento->diffInDays(now()->startOfDay());
    }

    public function agingBucket(): string
    {
        if (! $this->isOverdue()) {
            return 'em_dia';
        }

        $days = $this->daysOverdue();

        return match (true) {
            $days <= 30 => 'ate_30',
            $days <= 60 => 'ate_60',
            $days <= 90 => 'ate_90',
            default => 'acima_90',
        };
    }

    public function parcelLabel(): string
    {
        if ($this->total_parcelas <= 1) {
            return 'Única';
        }

        return "{$this->parcela}/{$this->total_parcelas}";
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReceivableTitleStatus::Aberto->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->open()
            ->whereDate('vencimento', '<', now()->toDateString());
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', ReceivableTitleStatus::Pago->value);
    }

    public function scopeNotExportedToErp(Builder $query): Builder
    {
        return $query->whereNull('exportado_erp_em');
    }

    public function scopeExportedToErp(Builder $query): Builder
    {
        return $query->whereNotNull('exportado_erp_em');
    }
}
