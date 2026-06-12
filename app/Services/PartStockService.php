<?php

namespace App\Services;

use App\Enums\PartStockMovementType;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Maintenance\MaintenancePart;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Maintenance\PartStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PartStockService
{
    public function deductForCompletedOrder(MaintenanceOrder $order, ?User $user = null): void
    {
        if (! config('maintenance.auto_deduct_parts_on_complete', true)) {
            return;
        }

        $user ??= auth()->user();
        $order->loadMissing(['parts.catalogItem']);

        $parts = $order->parts
            ->filter(fn (MaintenancePart $part) => $part->part_catalog_item_id !== null && ! $part->estoque_baixado);

        if ($parts->isEmpty()) {
            return;
        }

        $this->assertSufficientStock($parts);

        foreach ($parts as $part) {
            $this->deductPart($part, $order, $user);
        }
    }

    /** @param iterable<int, MaintenancePart> $parts */
    public function assertSufficientStock(iterable $parts): void
    {
        $shortages = [];

        foreach ($parts as $part) {
            if ($part->part_catalog_item_id === null || $part->estoque_baixado) {
                continue;
            }

            $item = $part->catalogItem ?? PartCatalogItem::query()->find($part->part_catalog_item_id);

            if ($item === null) {
                continue;
            }

            $required = (float) $part->quantidade;
            $available = (float) $item->estoque_atual;

            if ($available < $required && ! config('maintenance.allow_negative_part_stock', false)) {
                $shortages[] = "{$item->codigo_peca} (necessário {$required}, disponível {$available})";
            }
        }

        if ($shortages !== []) {
            throw new InvalidArgumentException(
                'Estoque insuficiente para concluir a OS: '.implode('; ', $shortages).'.'
            );
        }
    }

    public function recordPurchaseEntry(
        PartCatalogItem $item,
        float $quantity,
        ?PartPurchaseOrder $purchaseOrder = null,
        ?User $user = null,
        ?string $observacao = null,
    ): PartCatalogItem {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantidade de entrada deve ser maior que zero.');
        }

        $user ??= auth()->user();

        return DB::transaction(function () use ($item, $quantity, $purchaseOrder, $user, $observacao) {
            $locked = PartCatalogItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $previous = (float) $locked->estoque_atual;
            $next = round($previous + $quantity, 2);

            $locked->update(['estoque_atual' => $next]);

            $this->createMovement(
                $locked,
                PartStockMovementType::Entrada,
                $quantity,
                $previous,
                $next,
                null,
                null,
                $user,
                $observacao ?? ($purchaseOrder ? "Entrada — pedido {$purchaseOrder->codigo}" : 'Entrada de estoque'),
            );

            return $locked->fresh();
        });
    }

    public function recordManualAdjustment(
        PartCatalogItem $item,
        float $newBalance,
        ?string $observacao = null,
        ?User $user = null,
    ): PartCatalogItem {
        $user ??= auth()->user();

        return DB::transaction(function () use ($item, $newBalance, $observacao, $user) {
            $locked = PartCatalogItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $previous = (float) $locked->estoque_atual;
            $delta = round($newBalance - $previous, 2);

            if ($delta === 0.0) {
                return $locked;
            }

            $locked->update(['estoque_atual' => $newBalance]);

            $this->createMovement(
                $locked,
                PartStockMovementType::Ajuste,
                abs($delta),
                $previous,
                $newBalance,
                null,
                null,
                $user,
                $observacao ?? 'Ajuste manual de estoque',
            );

            return $locked->fresh();
        });
    }

    private function deductPart(MaintenancePart $part, MaintenanceOrder $order, ?User $user): void
    {
        DB::transaction(function () use ($part, $order, $user) {
            $part = MaintenancePart::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();

            if ($part->estoque_baixado || $part->part_catalog_item_id === null) {
                return;
            }

            $item = PartCatalogItem::query()
                ->whereKey($part->part_catalog_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (float) $part->quantidade;
            $previous = (float) $item->estoque_atual;
            $next = round($previous - $quantity, 2);

            if ($next < 0 && ! config('maintenance.allow_negative_part_stock', false)) {
                throw new InvalidArgumentException(
                    "Estoque insuficiente para {$item->codigo_peca}."
                );
            }

            $item->update(['estoque_atual' => $next]);
            $part->update(['estoque_baixado' => true]);

            $this->createMovement(
                $item,
                PartStockMovementType::SaidaOs,
                $quantity,
                $previous,
                $next,
                $order,
                $part,
                $user,
                "Baixa automática — OS {$order->codigo}",
            );
        });
    }

    private function createMovement(
        PartCatalogItem $item,
        PartStockMovementType $type,
        float $quantity,
        float $previousBalance,
        float $newBalance,
        ?MaintenanceOrder $order,
        ?MaintenancePart $part,
        ?User $user,
        ?string $observacao,
    ): PartStockMovement {
        return PartStockMovement::create([
            'operating_company_id' => $item->operating_company_id,
            'part_catalog_item_id' => $item->id,
            'maintenance_order_id' => $order?->id,
            'maintenance_part_id' => $part?->id,
            'tipo' => $type->value,
            'quantidade' => $quantity,
            'saldo_anterior' => $previousBalance,
            'saldo_posterior' => $newBalance,
            'user_id' => $user?->id,
            'observacao' => $observacao,
        ]);
    }
}
