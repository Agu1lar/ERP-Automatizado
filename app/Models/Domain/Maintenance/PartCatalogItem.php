<?php

namespace App\Models\Domain\Maintenance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PartCatalogItem extends Model
{
    protected $fillable = [
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
