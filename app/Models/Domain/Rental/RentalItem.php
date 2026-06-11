<?php

namespace App\Models\Domain\Rental;

use App\Models\Domain\Fleet\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItem extends Model
{
    protected $fillable = [
        'rental_id',
        'asset_id',
        'descricao',
        'quantidade',
        'valor_locacao',
        'valor_indenizacao',
        'devolvido',
        'devolvido_em',
        'local_entrega',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'valor_locacao' => 'decimal:2',
            'valor_indenizacao' => 'decimal:2',
            'devolvido' => 'boolean',
            'devolvido_em' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class)->withoutGlobalScope('operating_company');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScope('operating_company');
    }

    public function totalLocacao(): float
    {
        return round((float) $this->valor_locacao * $this->quantidade, 2);
    }

    public function totalIndenizacao(): float
    {
        return round((float) ($this->valor_indenizacao ?? 0) * $this->quantidade, 2);
    }
}
