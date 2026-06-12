<?php

namespace App\Models\Domain\Logistics;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DeliveryProof extends Model
{
    protected $fillable = [
        'delivery_manifest_stop_id',
        'receptor_nome',
        'assinatura_imagem',
        'foto_path',
        'observacoes',
        'user_id',
        'registrado_em',
    ];

    protected function casts(): array
    {
        return [
            'registrado_em' => 'datetime',
        ];
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(DeliveryManifestStop::class, 'delivery_manifest_stop_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fotoUrl(): ?string
    {
        if (blank($this->foto_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->foto_path);
    }
}
