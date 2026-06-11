<?php

namespace App\Models\Domain\Rental;

use App\Enums\RentalStatus;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Attachment\Attachment;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;
use App\Support\OperatingCompanyRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Rental extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'asset_id',
        'customer_id',
        'status',
        'reserved_at',
        'reserved_by',
        'commercial_user_id',
        'checkout_at',
        'checkout_by',
        'expected_return_at',
        'returned_at',
        'returned_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'observacoes',
        'horimetro_saida',
        'horimetro_retorno',
        'ficha_descricao',
        'local_obra',
        'valor_frete_entrega',
        'valor_frete_recolhida',
        'localizacao_origem',
        'valor_faturamento',
        'pricing_period',
        'billed_days',
        'valor_calculado',
        'billing_cycle_days',
        'billing_min_amount',
        'billing_period_start',
        'billing_period_end',
        'last_billed_at',
        'next_billing_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'checkout_at' => 'datetime',
            'expected_return_at' => 'date',
            'returned_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'horimetro_saida' => 'decimal:2',
            'horimetro_retorno' => 'decimal:2',
            'valor_faturamento' => 'decimal:2',
            'valor_calculado' => 'decimal:2',
            'valor_frete_entrega' => 'decimal:2',
            'valor_frete_recolhida' => 'decimal:2',
            'billing_cycle_days' => 'integer',
            'billing_min_amount' => 'decimal:2',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'last_billed_at' => 'date',
            'next_billing_at' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, Asset::class, 'asset');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function commercialUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function checkoutByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkout_by');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(RentalChecklist::class)->latest();
    }

    public function receivableTitles(): HasMany
    {
        return $this->hasMany(ReceivableTitle::class)->orderBy('parcela');
    }

    public function assetSubstitutions(): HasMany
    {
        return $this->hasMany(RentalAssetSubstitution::class)->latest('substituted_at');
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest('opened_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('ativo', true);
    }

    public function billingQueueEntries(): HasMany
    {
        return $this->hasMany(RentalBillingQueueEntry::class)->latest('gerado_em');
    }

    public function pendingBillingEntries(): HasMany
    {
        return $this->billingQueueEntries()->pendingInvoice();
    }

    public function statusEnum(): RentalStatus
    {
        return RentalStatus::from($this->status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RentalStatus::Reservado->value,
            RentalStatus::Locado->value,
            RentalStatus::EmInspecao->value,
        ]);
    }

    public function scopePendingCheckout(Builder $query): Builder
    {
        return $query->where('status', RentalStatus::Reservado->value);
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query
            ->where('status', RentalStatus::Locado->value)
            ->whereNotNull('expected_return_at')
            ->whereDate('expected_return_at', now()->toDateString());
    }

    public function scopeOverdueReturns(Builder $query): Builder
    {
        return $query
            ->where('status', RentalStatus::Locado->value)
            ->whereNotNull('expected_return_at')
            ->whereDate('expected_return_at', '<', now()->toDateString());
    }

    public function scopeBillingCycleDue(Builder $query): Builder
    {
        return $query
            ->where('status', RentalStatus::Locado->value)
            ->whereNotNull('next_billing_at')
            ->whereDate('next_billing_at', '<=', now()->toDateString());
    }

    public function isReturnOverdue(): bool
    {
        return $this->status === RentalStatus::Locado->value
            && $this->expected_return_at !== null
            && $this->expected_return_at->lt(now()->startOfDay());
    }

    public function daysOverdue(): ?int
    {
        if (! $this->isReturnOverdue()) {
            return null;
        }

        return (int) $this->expected_return_at->diffInDays(now()->startOfDay());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScope('operating_company')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
