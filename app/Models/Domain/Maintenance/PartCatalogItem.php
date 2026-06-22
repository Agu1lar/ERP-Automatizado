<?php

namespace App\Models\Domain\Maintenance;

use App\Models\Concerns\BelongsToOperatingCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartCatalogItem extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'codigo_peca',
        'codigo_alternativo',
        'descricao',
        'valor_unitario_padrao',
        'estoque_atual',
        'estoque_minimo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor_unitario_padrao' => 'decimal:2',
            'estoque_atual' => 'decimal:2',
            'estoque_minimo' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(PartStockMovement::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(PartCatalogSupplierPrice::class)->latest();
    }

    public function scopeBelowMinimum(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->whereNotNull('estoque_minimo')
            ->whereColumn('estoque_atual', '<', 'estoque_minimo');
    }

    public function isBelowMinimum(): bool
    {
        if ($this->estoque_minimo === null) {
            return false;
        }

        return (float) $this->estoque_atual < (float) $this->estoque_minimo;
    }
}
