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
        'valor_contratado',
        'valor_indenizacao',
        'devolvido',
        'devolvido_em',
        'local_entrega',
        'horimetro_entrada',
        'horimetro_saida',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'valor_locacao' => 'decimal:2',
            'valor_contratado' => 'decimal:2',
            'valor_indenizacao' => 'decimal:2',
            'horimetro_entrada' => 'decimal:2',
            'horimetro_saida' => 'decimal:2',
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

    /** Valor acordado no contrato para faturamento recorrente. */
    public function billingRate(): float
    {
        if ($this->valor_contratado !== null && (float) $this->valor_contratado > 0) {
            return round((float) $this->valor_contratado, 2);
        }

        return round((float) $this->valor_locacao, 2);
    }
}
