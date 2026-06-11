<?php

namespace App\Models\Domain\Finance;

use App\Enums\LateFeeRuleScope;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Support\OperatingCompanyRelations;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Rental\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFeeRule extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'escopo',
        'customer_id',
        'rental_id',
        'nome',
        'multa_percent',
        'juros_mensal_percent',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'multa_percent' => 'decimal:4',
            'juros_mensal_percent' => 'decimal:4',
            'ativo' => 'boolean',
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

    public function escopoEnum(): LateFeeRuleScope
    {
        return LateFeeRuleScope::from($this->escopo);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('escopo', LateFeeRuleScope::Global->value);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query
            ->where('escopo', LateFeeRuleScope::Customer->value)
            ->where('customer_id', $customerId);
    }

    public function scopeForRental(Builder $query, int $rentalId): Builder
    {
        return $query
            ->where('escopo', LateFeeRuleScope::Rental->value)
            ->where('rental_id', $rentalId);
    }

    public function targetLabel(): string
    {
        return match ($this->escopoEnum()) {
            LateFeeRuleScope::Global => 'Global',
            LateFeeRuleScope::Customer => $this->customer?->nome ?? 'Cliente',
            LateFeeRuleScope::Rental => $this->rental?->codigo ?? 'Locação',
        };
    }
}
