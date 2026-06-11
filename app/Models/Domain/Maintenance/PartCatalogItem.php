<?php

namespace App\Models\Domain\Maintenance;

use App\Models\Concerns\BelongsToOperatingCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PartCatalogItem extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo_peca',
        'codigo_alternativo',
        'descricao',
        'valor_unitario_padrao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor_unitario_padrao' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
