<?php

namespace App\Models\Domain\Fleet;

use App\Enums\RentalPricingPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentPricing extends Model
{
    protected $fillable = [
        'equipment_model_id',
        'equipment_category_id',
        'periodo',
        'valor',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function equipmentModel(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function periodEnum(): RentalPricingPeriod
    {
        return RentalPricingPeriod::from($this->periodo);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function targetLabel(): string
    {
        if ($this->equipment_model_id) {
            return $this->equipmentModel->displayName();
        }

        return $this->category->nome ?? '—';
    }
}
