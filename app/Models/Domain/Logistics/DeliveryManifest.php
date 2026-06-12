<?php

namespace App\Models\Domain\Logistics;

use App\Enums\DeliveryManifestStatus;
use App\Models\Concerns\BelongsToOperatingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryManifest extends Model
{
    use BelongsToOperatingCompany;

    protected $fillable = [
        'operating_company_id',
        'codigo',
        'data',
        'delivery_driver_id',
        'delivery_vehicle_id',
        'status',
        'observacoes',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(DeliveryVehicle::class, 'delivery_vehicle_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryManifestStop::class)->orderBy('sequencia');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusEnum(): DeliveryManifestStatus
    {
        return DeliveryManifestStatus::from($this->status);
    }

    public function completedStopsCount(): int
    {
        return $this->stops()->where('status', \App\Enums\DeliveryManifestStopStatus::Concluida->value)->count();
    }
}
