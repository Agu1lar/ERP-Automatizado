<?php

namespace App\Models\Domain\Rental;

use App\Models\Domain\Fleet\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAssetSubstitution extends Model
{
    protected $fillable = [
        'rental_id',
        'from_asset_id',
        'to_asset_id',
        'motivo',
        'substituted_by',
        'substituted_at',
    ];

    protected function casts(): array
    {
        return [
            'substituted_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class)->withoutGlobalScope('operating_company');
    }

    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'from_asset_id')->withoutGlobalScope('operating_company');
    }

    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'to_asset_id')->withoutGlobalScope('operating_company');
    }

    public function substitutedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substituted_by');
    }
}
