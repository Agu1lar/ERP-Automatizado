<?php

namespace App\Models\Domain\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\Rental;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Models\User;
use App\Support\OperatingCompanyRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocument extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'rental_id',
        'receivable_title_id',
        'billing_queue_entry_id',
        'codigo',
        'tipo',
        'status',
        'valor',
        'descricao',
        'erp_provider',
        'erp_external_id',
        'erp_payload',
        'erro_mensagem',
        'enviado_erp_em',
        'enviado_erp_por',
        'emitido_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'erp_payload' => 'array',
            'enviado_erp_em' => 'datetime',
            'emitido_em' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, Rental::class, 'rental');
    }

    public function receivableTitle(): BelongsTo
    {
        return $this->belongsTo(ReceivableTitle::class);
    }

    public function billingQueueEntry(): BelongsTo
    {
        return $this->belongsTo(RentalBillingQueueEntry::class, 'billing_queue_entry_id');
    }

    public function enviadoErpByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_erp_por');
    }

    public function typeEnum(): FiscalDocumentType
    {
        return FiscalDocumentType::from($this->tipo);
    }

    public function statusEnum(): FiscalDocumentStatus
    {
        return FiscalDocumentStatus::from($this->status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FiscalDocumentStatus::Pendente->value);
    }
}
