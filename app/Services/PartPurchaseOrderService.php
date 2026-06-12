<?php

namespace App\Services;

use App\Enums\CompanyType;
use App\Enums\PartPurchaseOrderStatus;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Maintenance\PartPurchaseOrderItem;
use App\Models\Domain\Person\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PartPurchaseOrderService
{
    public function __construct(
        private readonly PartStockService $partStockService,
        private readonly PartSupplierPriceService $supplierPriceService,
        private readonly PayableTitleService $payableTitleService,
        private readonly AuditService $auditService,
    ) {}

    /** @return Collection<int, PartCatalogItem> */
    public function lowStockItems(): Collection
    {
        return PartCatalogItem::query()
            ->belowMinimum()
            ->orderBy('descricao')
            ->get();
    }

    /** @return Collection<int, Company> */
    public function supplierOptions(): Collection
    {
        return Company::query()
            ->where('ativo', true)
            ->where('tipo', CompanyType::Fornecedor->value)
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  list<array{part_catalog_item_id: int, quantidade: float, valor_unitario?: float|null}>  $items
     */
    public function create(
        Company $supplier,
        array $items,
        ?string $observacoes = null,
        ?User $user = null,
    ): PartPurchaseOrder {
        $user ??= auth()->user();

        if ($supplier->tipo !== CompanyType::Fornecedor->value) {
            throw new InvalidArgumentException('Selecione um fornecedor de peças cadastrado.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('Informe ao menos uma peça no pedido.');
        }

        return DB::transaction(function () use ($supplier, $items, $observacoes, $user) {
            $order = PartPurchaseOrder::create([
                'codigo' => $this->generateCodigo(),
                'company_id' => $supplier->id,
                'status' => PartPurchaseOrderStatus::Rascunho->value,
                'observacoes' => $observacoes,
                'created_by' => $user?->id,
            ]);

            foreach ($items as $row) {
                $item = PartCatalogItem::query()->findOrFail($row['part_catalog_item_id']);
                $qty = (float) $row['quantidade'];

                if ($qty <= 0) {
                    throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
                }

                $order->items()->create([
                    'part_catalog_item_id' => $item->id,
                    'quantidade_pedida' => $qty,
                    'valor_unitario' => isset($row['valor_unitario'])
                        ? round((float) $row['valor_unitario'], 2)
                        : $this->supplierPriceService->latestForSupplier($item, $supplier)?->valor_unitario
                            ?? $item->valor_unitario_padrao,
                ]);
            }

            return $order->fresh(['items.catalogItem', 'supplier']);
        });
    }

    public function createFromLowStock(Company $supplier, ?User $user = null): PartPurchaseOrder
    {
        $lowStock = $this->lowStockItems();

        if ($lowStock->isEmpty()) {
            throw new InvalidArgumentException('Nenhuma peça abaixo do estoque mínimo.');
        }

        $items = $lowStock->map(function (PartCatalogItem $item) {
            $needed = max(0, (float) $item->estoque_minimo - (float) $item->estoque_atual);

            return [
                'part_catalog_item_id' => $item->id,
                'quantidade' => $needed > 0 ? $needed : 1,
            ];
        })->all();

        return $this->create(
            $supplier,
            $items,
            'Pedido gerado automaticamente — peças abaixo do estoque mínimo.',
            $user,
        );
    }

    public function markSent(PartPurchaseOrder $order, ?User $user = null): PartPurchaseOrder
    {
        $user ??= auth()->user();

        if ($order->statusEnum() !== PartPurchaseOrderStatus::Rascunho) {
            throw new InvalidArgumentException('Somente pedidos em rascunho podem ser enviados.');
        }

        $order->update([
            'status' => PartPurchaseOrderStatus::Enviado->value,
            'pedido_em' => now()->toDateString(),
        ]);

        return $order->fresh();
    }

    public function receive(PartPurchaseOrder $order, ?User $user = null): PartPurchaseOrder
    {
        $user ??= auth()->user();

        if (! in_array($order->statusEnum(), [
            PartPurchaseOrderStatus::Enviado,
            PartPurchaseOrderStatus::RecebidoParcial,
        ], true)) {
            throw new InvalidArgumentException('Pedido não está aguardando recebimento.');
        }

        return DB::transaction(function () use ($order, $user) {
            $order = $order->fresh(['items.catalogItem', 'supplier']);

            foreach ($order->items as $line) {
                $pending = $line->pendingQuantity();

                if ($pending <= 0) {
                    continue;
                }

                $catalogItem = $line->catalogItem;
                $unitPrice = (float) ($line->valor_unitario ?? $catalogItem->valor_unitario_padrao ?? 0);

                $this->partStockService->recordPurchaseEntry(
                    $catalogItem,
                    $pending,
                    $order,
                    $user,
                    "Recebimento — pedido {$order->codigo}",
                );

                if ($unitPrice > 0) {
                    $this->supplierPriceService->record(
                        $catalogItem,
                        $order->supplier,
                        $unitPrice,
                        $order,
                        "Recebimento — pedido {$order->codigo}",
                        $user,
                    );
                }

                $line->update([
                    'quantidade_recebida' => (float) $line->quantidade_pedida,
                ]);
            }

            $order->update([
                'status' => PartPurchaseOrderStatus::Recebido->value,
                'recebido_em' => now()->toDateString(),
                'received_by' => $user?->id,
            ]);

            $order = $order->fresh(['items.catalogItem', 'supplier']);

            if (! $order->payableTitle && $order->totalValue() > 0) {
                $this->payableTitleService->createFromPurchaseOrder(
                    $order,
                    now()->addDays(30),
                    $user,
                );
            }

            return $order->fresh(['items.catalogItem', 'supplier', 'payableTitle']);
        });
    }

    public function cancel(PartPurchaseOrder $order, ?User $user = null): PartPurchaseOrder
    {
        if ($order->statusEnum() === PartPurchaseOrderStatus::Recebido) {
            throw new InvalidArgumentException('Pedido já recebido não pode ser cancelado.');
        }

        $order->update(['status' => PartPurchaseOrderStatus::Cancelado->value]);

        return $order->fresh();
    }

    private function generateCodigo(): string
    {
        $next = (PartPurchaseOrder::withoutGlobalScope('operating_company')->max('id') ?? 0) + 1;

        return 'PC-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
