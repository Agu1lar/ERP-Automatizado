<?php

namespace App\Models\Domain\Rental;

use App\Enums\RentalQuoteStatus;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\User;
use App\Support\OperatingCompanyRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalQuote extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'asset_id',
        'customer_id',
        'status',
        'valid_until',
        'sent_at',
        'expected_return_at',
        'local_obra',
        'observacoes',
        'pricing_period',
        'valor_estimado',
        'rental_id',
        'created_by',
        'converted_at',
        'converted_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
            'sent_at' => 'datetime',
            'expected_return_at' => 'date',
            'valor_estimado' => 'decimal:2',
            'converted_at' => 'datetime',
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

    public function rental(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, Rental::class, 'rental');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function statusEnum(): RentalQuoteStatus
    {
        return RentalQuoteStatus::from($this->status);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->valid_until === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false);
    }
}
