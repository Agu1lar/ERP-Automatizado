<?php

namespace App\Models\Domain\Maintenance;

use App\Models\Concerns\BelongsToOperatingCompany;
use App\Support\OperatingCompanyRelations;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreventiveMaintenanceRule extends Model
{
    use BelongsToOperatingCompany, SoftDeletes;

    protected $fillable = [
        'operating_company_id',
        'equipment_model_id',
        'interval_horas',
        'descricao',
        'ativo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'interval_horas' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function equipmentModel(): BelongsTo
    {
        return OperatingCompanyRelations::belongsTo($this, EquipmentModel::class, 'equipmentModel');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'preventive_rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
