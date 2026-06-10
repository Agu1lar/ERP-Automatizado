<?php

namespace App\Support;

use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use Illuminate\Support\Collection;

class AssetTimeline
{
    /** @return Collection<int, array{tipo: string, titulo: string, detalhe: string|null, usuario: string|null, data: \Carbon\Carbon}> */
    public static function for(Asset $asset): Collection
    {
        $events = collect();

        foreach ($asset->statusHistories as $history) {
            $anterior = $history->status_anterior
                ? AssetStatus::from($history->status_anterior)->label()
                : 'Cadastro';
            $novo = AssetStatus::from($history->status_novo)->label();

            $events->push([
                'tipo' => 'status',
                'titulo' => "{$anterior} → {$novo}",
                'detalhe' => $history->motivo,
                'usuario' => $history->user?->name,
                'data' => $history->created_at,
            ]);
        }

        foreach ($asset->movements as $movement) {
            $events->push([
                'tipo' => 'localizacao',
                'titulo' => 'Movimentação de localização',
                'detalhe' => trim(($movement->origem ?? '—').' → '.($movement->destino ?? '—').($movement->motivo ? " — {$movement->motivo}" : '')),
                'usuario' => $movement->user?->name,
                'data' => $movement->created_at,
            ]);
        }

        foreach ($asset->relationLoaded('rentals') ? $asset->rentals : collect() as $rental) {
            /** @var Rental $rental */
            $events->push([
                'tipo' => 'locacao',
                'titulo' => "Locação {$rental->codigo} — {$rental->statusEnum()->label()}",
                'detalhe' => $rental->customer?->nome,
                'usuario' => $rental->reservedByUser?->name,
                'data' => $rental->reserved_at,
            ]);

            if ($rental->checkout_at) {
                $events->push([
                    'tipo' => 'locacao',
                    'titulo' => "Saída {$rental->codigo}",
                    'detalhe' => $rental->customer?->nome,
                    'usuario' => $rental->checkoutByUser?->name,
                    'data' => $rental->checkout_at,
                ]);
            }

            if ($rental->returned_at) {
                $events->push([
                    'tipo' => 'locacao',
                    'titulo' => "Retorno {$rental->codigo}",
                    'detalhe' => null,
                    'usuario' => $rental->returnedByUser?->name,
                    'data' => $rental->returned_at,
                ]);
            }
        }

        foreach ($asset->relationLoaded('maintenanceOrders') ? $asset->maintenanceOrders : collect() as $order) {
            /** @var MaintenanceOrder $order */
            $events->push([
                'tipo' => 'manutencao',
                'titulo' => "OS {$order->codigo} — {$order->statusEnum()->label()}",
                'detalhe' => $order->descricao_problema,
                'usuario' => $order->openedByUser?->name,
                'data' => $order->opened_at,
            ]);

            if ($order->completed_at) {
                $events->push([
                    'tipo' => 'manutencao',
                    'titulo' => "OS {$order->codigo} concluída",
                    'detalhe' => $order->solucao_aplicada,
                    'usuario' => $order->completedByUser?->name,
                    'data' => $order->completed_at,
                ]);
            }
        }

        return $events->sortByDesc('data')->values();
    }
}
