<?php

namespace App\Livewire\Maintenance;

use App\Enums\PartPurchaseOrderStatus;
use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Models\Domain\Maintenance\PartPurchaseOrder;
use App\Models\Domain\Person\Company;
use App\Services\PartPurchaseOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PartPurchaseOrderIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $supplier_id = null;

    public string $observacoes = '';

    /** @var array<int, array{part_catalog_item_id: int, quantidade: string, valor_unitario: string}> */
    public array $items = [];

    public function mount(): void
    {
        $this->authorize('viewAny', PartPurchaseOrder::class);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', PartPurchaseOrder::class);
        $this->resetForm();
        $this->items = [[
            'part_catalog_item_id' => 0,
            'quantidade' => '1',
            'valor_unitario' => '',
        ]];
        $this->showForm = true;
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'part_catalog_item_id' => 0,
            'quantidade' => '1',
            'valor_unitario' => '',
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(PartPurchaseOrderService $service): void
    {
        $this->authorize('create', PartPurchaseOrder::class);

        $data = $this->validate([
            'supplier_id' => 'required|exists:companies,id',
            'observacoes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.part_catalog_item_id' => 'required|exists:part_catalog_items,id',
            'items.*.quantidade' => 'required|numeric|min:0.01',
            'items.*.valor_unitario' => 'nullable|numeric|min:0',
        ]);

        $supplier = Company::findOrFail($data['supplier_id']);

        try {
            $service->create(
                $supplier,
                collect($data['items'])->map(fn (array $row) => [
                    'part_catalog_item_id' => (int) $row['part_catalog_item_id'],
                    'quantidade' => (float) $row['quantidade'],
                    'valor_unitario' => filled($row['valor_unitario']) ? (float) $row['valor_unitario'] : null,
                ])->all(),
                $data['observacoes'] ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('supplier_id', $e->getMessage());

            return;
        }

        $this->resetForm();
        session()->flash('success', 'Pedido de compra criado.');
    }

    public function createFromLowStock(PartPurchaseOrderService $service): void
    {
        $this->authorize('create', PartPurchaseOrder::class);

        if (! $this->supplier_id) {
            $this->addError('supplier_id', 'Selecione o fornecedor antes de gerar o pedido.');

            return;
        }

        try {
            $service->createFromLowStock(Company::findOrFail($this->supplier_id));
        } catch (\InvalidArgumentException $e) {
            $this->addError('supplier_id', $e->getMessage());

            return;
        }

        session()->flash('success', 'Pedido gerado com peças abaixo do estoque mínimo.');
    }

    public function send(int $id, PartPurchaseOrderService $service): void
    {
        $order = PartPurchaseOrder::findOrFail($id);
        $this->authorize('update', $order);

        try {
            $service->markSent($order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'Pedido marcado como enviado.');
    }

    public function receive(int $id, PartPurchaseOrderService $service): void
    {
        $order = PartPurchaseOrder::findOrFail($id);
        $this->authorize('update', $order);

        try {
            $service->receive($order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'Pedido recebido e estoque atualizado.');
    }

    public function cancel(int $id, PartPurchaseOrderService $service): void
    {
        $order = PartPurchaseOrder::findOrFail($id);
        $this->authorize('update', $order);

        try {
            $service->cancel($order);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'Pedido cancelado.');
    }

    public function render(): View
    {
        $service = app(PartPurchaseOrderService::class);

        $orders = PartPurchaseOrder::query()
            ->with('payableTitle')
            ->with(['supplier', 'items'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.maintenance.part-purchase-order-index', [
            'orders' => $orders,
            'suppliers' => $service->supplierOptions(),
            'catalogItems' => PartCatalogItem::query()->where('ativo', true)->orderBy('descricao')->get(),
            'lowStockItems' => $service->lowStockItems(),
            'statusOptions' => PartPurchaseOrderStatus::cases(),
            'canManage' => auth()->user()->can('create', PartPurchaseOrder::class),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->supplier_id = null;
        $this->observacoes = '';
        $this->items = [];
        $this->resetValidation();
    }
}
