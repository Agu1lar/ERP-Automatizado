<?php

namespace App\Models\Domain\Logistics;

use App\Models\Concerns\BelongsToOperatingCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryDriver extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'nome',
        'cnh',
        'telefone',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(DeliveryManifest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
