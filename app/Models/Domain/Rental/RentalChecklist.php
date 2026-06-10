<?php

namespace App\Models\Domain\Rental;

use App\Enums\RentalChecklistType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalChecklist extends Model
{
    protected $fillable = [
        'rental_id',
        'tipo',
        'user_id',
        'observacoes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalChecklistItem::class);
    }

    public function tipoEnum(): RentalChecklistType
    {
        return RentalChecklistType::from($this->tipo);
    }
}
