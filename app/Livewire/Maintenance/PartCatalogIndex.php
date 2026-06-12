<?php

namespace App\Livewire\Maintenance;

use App\Models\Domain\Maintenance\PartCatalogItem;
use App\Services\PartStockService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PartCatalogIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $codigo_peca = '';

    public string $codigo_alternativo = '';

    public string $descricao = '';

    public string $valor_unitario_padrao = '';

    public string $estoque_atual = '0';

    public string $estoque_minimo = '';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', PartCatalogItem::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', PartCatalogItem::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = PartCatalogItem::findOrFail($id);
        $this->authorize('update', $item);

        $this->editingId = $item->id;
        $this->codigo_peca = $item->codigo_peca;
        $this->codigo_alternativo = $item->codigo_alternativo ?? '';
        $this->descricao = $item->descricao;
        $this->valor_unitario_padrao = $item->valor_unitario_padrao !== null ? (string) $item->valor_unitario_padrao : '';
        $this->estoque_atual = (string) $item->estoque_atual;
        $this->estoque_minimo = $item->estoque_minimo !== null ? (string) $item->estoque_minimo : '';
        $this->ativo = $item->ativo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'codigo_peca' => 'required|string|max:100|unique:part_catalog_items,codigo_peca'.($this->editingId ? ','.$this->editingId : ''),
            'codigo_alternativo' => 'nullable|string|max:100',
            'descricao' => 'required|string|max:255',
            'valor_unitario_padrao' => 'nullable|numeric|min:0',
            'estoque_atual' => 'required|numeric|min:0',
            'estoque_minimo' => 'nullable|numeric|min:0',
            'ativo' => 'boolean',
        ]);

        $payload = [
            'codigo_peca' => $data['codigo_peca'],
            'codigo_alternativo' => $data['codigo_alternativo'] ?: null,
            'descricao' => $data['descricao'],
            'valor_unitario_padrao' => $data['valor_unitario_padrao'] !== '' ? $data['valor_unitario_padrao'] : null,
            'estoque_minimo' => $data['estoque_minimo'] !== '' ? $data['estoque_minimo'] : null,
            'ativo' => $data['ativo'],
        ];

        if ($this->editingId) {
            $item = PartCatalogItem::findOrFail($this->editingId);
            $this->authorize('update', $item);
            $item->update($payload);

            $newBalance = (float) $data['estoque_atual'];
            if ((float) $item->estoque_atual !== $newBalance) {
                app(PartStockService::class)->recordManualAdjustment($item->fresh(), $newBalance, 'Ajuste pelo catálogo');
            }
        } else {
            $this->authorize('create', PartCatalogItem::class);
            PartCatalogItem::create([
                ...$payload,
                'estoque_atual' => $data['estoque_atual'],
            ]);
        }

        $this->resetForm();
        session()->flash('success', 'Peça salva no catálogo.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        $items = PartCatalogItem::query()
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('codigo_peca', 'like', $term)
                        ->orWhere('codigo_alternativo', 'like', $term)
                        ->orWhere('descricao', 'like', $term);
                });
            })
            ->orderBy('descricao')
            ->paginate(20);

        $priceHistory = $this->editingId
            ? PartCatalogItem::with(['supplierPrices.supplier'])->find($this->editingId)?->supplierPrices ?? collect()
            : collect();

        return view('livewire.maintenance.part-catalog-index', [
            'items' => $items,
            'canManage' => auth()->user()->can('create', PartCatalogItem::class),
            'priceHistory' => $priceHistory,
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->codigo_peca = '';
        $this->codigo_alternativo = '';
        $this->descricao = '';
        $this->valor_unitario_padrao = '';
        $this->estoque_atual = '0';
        $this->estoque_minimo = '';
        $this->ativo = true;
        $this->resetValidation();
    }
}
