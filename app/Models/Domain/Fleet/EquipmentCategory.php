<?php

namespace App\Models\Domain\Fleet;

use App\Models\Concerns\BelongsToOperatingCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentCategory extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'nome',
        'tipo_linha',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(EquipmentModel::class);
    }

    public function activeModels(): HasMany
    {
        return $this->models()->where('ativo', true);
    }
}
