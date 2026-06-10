<?php

namespace App\Models\Domain\Rental;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalChecklistItem extends Model
{
    protected $fillable = [
        'rental_checklist_id',
        'item_key',
        'item_label',
        'checked',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'checked' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(RentalChecklist::class, 'rental_checklist_id');
    }
}
