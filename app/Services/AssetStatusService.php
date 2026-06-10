<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\AssetStatusHistory;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssetStatusService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function transition(Asset $asset, AssetStatus $newStatus, ?string $motivo = null, ?User $user = null): Asset
    {
        $user ??= auth()->user();
        $currentStatus = AssetStatus::from($asset->status);

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Transição inválida: {$currentStatus->label()} → {$newStatus->label()}"
            );
        }

        if ($newStatus === AssetStatus::Bloqueado && blank($motivo)) {
            throw new InvalidArgumentException('Motivo obrigatório para bloquear patrimônio.');
        }

        if ($newStatus === AssetStatus::Disponivel && $this->hasBlockingMaintenanceOrder($asset)) {
            throw new InvalidArgumentException('Patrimônio possui OS impeditiva aberta.');
        }

        return DB::transaction(function () use ($asset, $currentStatus, $newStatus, $motivo, $user) {
            $before = ['status' => $asset->status];

            $asset->status = $newStatus->value;

            if ($newStatus === AssetStatus::Bloqueado) {
                $asset->motivo_bloqueio = $motivo;
            } elseif ($currentStatus === AssetStatus::Bloqueado && $newStatus !== AssetStatus::Bloqueado) {
                $asset->motivo_bloqueio = null;
            }

            $asset->save();

            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'status_anterior' => $currentStatus->value,
                'status_novo' => $newStatus->value,
                'motivo' => $motivo,
                'user_id' => $user?->id,
            ]);

            $this->auditService->log(
                AuditAction::StatusChanged,
                'Asset',
                $asset->id,
                $before,
                ['status' => $newStatus->value, 'motivo' => $motivo],
                $user,
            );

            return $asset->fresh();
        });
    }

    public function createWithInitialStatus(Asset $asset, AssetStatus $initialStatus, ?string $motivo = null, ?User $user = null): Asset
    {
        if ($initialStatus === AssetStatus::Bloqueado && blank($motivo)) {
            throw new InvalidArgumentException('Motivo obrigatório para cadastrar patrimônio bloqueado.');
        }

        return DB::transaction(function () use ($asset, $initialStatus, $motivo, $user) {
            $asset->status = $initialStatus->value;

            if ($initialStatus === AssetStatus::Bloqueado) {
                $asset->motivo_bloqueio = $motivo;
            }

            $asset->save();

            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'status_anterior' => null,
                'status_novo' => $initialStatus->value,
                'motivo' => $motivo ?? 'Cadastro inicial',
                'user_id' => ($user ?? auth()->user())?->id,
            ]);

            return $asset;
        });
    }

    /** @return list<AssetStatus> */
    public function allowedTransitionsFor(Asset $asset): array
    {
        return AssetStatus::from($asset->status)->allowedTransitions();
    }

    private function hasBlockingMaintenanceOrder(Asset $asset): bool
    {
        return MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->where('impeditiva', true)
            ->open()
            ->exists();
    }
}
