<?php

namespace App\Models\Domain\Logistics;

use App\Models\Concerns\BelongsToOperatingCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryVehicle extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'placa',
        'descricao',
        'observacoes',
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

    public function displayLabel(): string
    {
        return "{$this->placa} — {$this->descricao}";
    }
}
