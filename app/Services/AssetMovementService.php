<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\AssetMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssetMovementService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function moveLocation(Asset $asset, string $destino, ?string $motivo = null, ?User $user = null): Asset
    {
        $user ??= auth()->user();
        $origem = $asset->localizacao;

        if ($origem === $destino) {
            throw new \InvalidArgumentException('A nova localização é igual à atual.');
        }

        return DB::transaction(function () use ($asset, $origem, $destino, $motivo, $user) {
            $before = ['localizacao' => $origem];

            $asset->update(['localizacao' => $destino]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'tipo' => 'localizacao',
                'origem' => $origem,
                'destino' => $destino,
                'motivo' => $motivo,
                'user_id' => $user?->id,
            ]);

            $this->auditService->log(
                AuditAction::Updated,
                'Asset',
                $asset->id,
                $before,
                ['localizacao' => $destino, 'motivo' => $motivo],
                $user,
            );

            return $asset->fresh();
        });
    }
}
