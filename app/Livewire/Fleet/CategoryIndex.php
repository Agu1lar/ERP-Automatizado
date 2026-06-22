<?php

namespace App\Livewire\Fleet;

use App\Enums\AssetStatus;
use App\Livewire\Concerns\ArchivesRecords;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Support\CategoryAssetBoard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CategoryIndex extends Component
{
    use ArchivesRecords, AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $tipo_linha = 'linha_leve';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', EquipmentCategory::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', EquipmentCategory::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = EquipmentCategory::findOrFail($id);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->nome = $category->nome;
        $this->tipo_linha = $category->tipo_linha;
        $this->ativo = $category->ativo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'tipo_linha' => 'required|string|max:100',
            'ativo' => 'boolean',
        ]);

        if ($this->editingId) {
            $category = EquipmentCategory::findOrFail($this->editingId);
            $this->authorize('update', $category);
            $category->update($data);
        } else {
            $this->authorize('create', EquipmentCategory::class);
            EquipmentCategory::create($data);
        }

        $this->resetForm();
        session()->flash('success', 'Categoria salva com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->nome = '';
        $this->tipo_linha = 'linha_leve';
        $this->ativo = true;
        $this->resetValidation();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $categoryIds
     * @return array<int, array{total: int, disponivel: int, locado: int, manutencao: int}>
     */
    private function assetCountsForCategories($categoryIds): array
    {
        if ($categoryIds->isEmpty()) {
            return [];
        }

        $rows = Asset::query()
            ->join('equipment_models', 'assets.equipment_model_id', '=', 'equipment_models.id')
            ->whereIn('equipment_models.equipment_category_id', $categoryIds)
            ->select('equipment_models.equipment_category_id as category_id', 'assets.status', DB::raw('count(*) as total'))
            ->groupBy('equipment_models.equipment_category_id', 'assets.status')
            ->get();

        $counts = [];

        foreach ($categoryIds as $categoryId) {
            $counts[$categoryId] = [
                'total' => 0,
                'disponivel' => 0,
                'locado' => 0,
                'manutencao' => 0,
            ];
        }

        foreach ($rows as $row) {
            $categoryId = (int) $row->category_id;
            $group = CategoryAssetBoard::resolveGroup(AssetStatus::from($row->status));

            if ($group === '') {
                continue;
            }

            $counts[$categoryId]['total'] += (int) $row->total;
            $counts[$categoryId][$group] += (int) $row->total;
        }

        return $counts;
    }

    public function render(): View
    {
        $categories = $this->archivableQuery(EquipmentCategory::class)
            ->when($this->search, fn ($q) => $q->where('nome', 'like', '%'.$this->search.'%'))
            ->withCount('models')
            ->orderBy('nome')
            ->paginate(15);

        $assetCounts = $this->assetCountsForCategories($categories->pluck('id'));

        return view('livewire.fleet.category-index', [
            'categories' => $categories,
            'assetCounts' => $assetCounts,
        ]);
    }
}
