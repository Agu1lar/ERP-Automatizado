<?php

namespace App\Models\Domain\Fleet;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    protected $fillable = [
        'asset_id',
        'tipo',
        'origem',
        'destino',
        'motivo',
        'user_id',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
