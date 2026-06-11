<?php

namespace App\Models\Domain\Rental;

use App\Enums\RentalBillingQueueStatus;
use App\Enums\RentalBillingQueueType;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Support\OperatingCompanyRelations;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalBillingQueueEntry extends Model
{
    use BelongsToOperatingCompany;

    protected $table = 'rental_billing_queue';

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'rental_id',
        'customer_id',
        'tipo',
        'periodo_inicio',
        'periodo_fim',
        'valor_nf',
        'valor_car',
        'status',
        'gerado_em',
        'autorizado_em',
        'autorizado_por',
        'faturado_em',
        'faturado_por',
        'receivable_title_id',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'valor_nf' => 'decimal:2',
            'valor_car' => 'decimal:2',
            'gerado_em' => 'datetime',
            'autorizado_em' => 'datetime',
            'faturado_em' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, Rental::class, 'rental');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivableTitle(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, ReceivableTitle::class, 'receivableTitle');
    }

    public function autorizadoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function faturadoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faturado_por');
    }

    public function tipoEnum(): RentalBillingQueueType
    {
        return RentalBillingQueueType::from($this->tipo);
    }

    public function statusEnum(): RentalBillingQueueStatus
    {
        return RentalBillingQueueStatus::from($this->status);
    }

    public function origemLabel(): string
    {
        return $this->rental?->codigo ?? '—';
    }

    public function scopePendingInvoice(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RentalBillingQueueStatus::Pendente->value,
            RentalBillingQueueStatus::Autorizado->value,
        ]);
    }

    public function scopeFaturavel(Builder $query): Builder
    {
        return $query->where('status', RentalBillingQueueStatus::Autorizado->value);
    }
}
