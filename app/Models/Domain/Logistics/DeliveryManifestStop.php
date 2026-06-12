<?php

namespace App\Models\Domain\Logistics;

use App\Enums\DeliveryManifestStopStatus;
use App\Enums\DeliveryManifestStopType;
use App\Models\Domain\Rental\Rental;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryManifestStop extends Model
{
    protected $fillable = [
        'delivery_manifest_id',
        'rental_id',
        'sequencia',
        'tipo',
        'status',
        'endereco',
        'turno',
        'observacoes',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(DeliveryManifest::class, 'delivery_manifest_id');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    public function tipoEnum(): DeliveryManifestStopType
    {
        return DeliveryManifestStopType::from($this->tipo);
    }

    public function statusEnum(): DeliveryManifestStopStatus
    {
        return DeliveryManifestStopStatus::from($this->status);
    }
}
