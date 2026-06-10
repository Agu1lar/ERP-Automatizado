<?php

namespace App\Models\Domain\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
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
