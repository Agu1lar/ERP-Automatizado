<?php

namespace App\Models\Domain\Fleet;

use App\Models\Concerns\BelongsToOperatingCompany;
use App\Support\OperatingCompanyRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentModel extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'equipment_category_id',
        'marca',
        'modelo',
        'especificacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'especificacoes' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, EquipmentCategory::class, 'category', 'equipment_category_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function preventiveRules(): HasMany
    {
        return $this->hasMany(\App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class);
    }

    public function displayName(): string
    {
        return "{$this->marca} {$this->modelo}";
    }
}
