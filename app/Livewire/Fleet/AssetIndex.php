<?php

namespace App\Livewire\Fleet;

use App\Enums\AssetStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Services\AssetMovementService;
use App\Services\AssetStatusService;
use App\Support\ActiveOperatingCompany;
use App\Support\TextSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AssetIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $codigo_patrimonio = '';

    public ?int $equipment_model_id = null;

    public string $serie = '';

    public ?string $valor_compra = null;

    public ?string $data_compra = null;

    public string $localizacao = '';

    public string $observacoes = '';

    public string $initial_status = 'disponivel';

    public string $motivo_bloqueio = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Asset::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Asset::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $asset = Asset::findOrFail($id);
        $this->authorize('update', $asset);

        $this->editingId = $asset->id;
        $this->codigo_patrimonio = $asset->codigo_patrimonio;
        $this->equipment_model_id = $asset->equipment_model_id;
        $this->serie = $asset->serie ?? '';
        $this->valor_compra = $asset->valor_compra !== null ? (string) $asset->valor_compra : null;
        $this->data_compra = $asset->data_compra?->format('Y-m-d');
        $this->localizacao = $asset->localizacao ?? '';
        $this->observacoes = $asset->observacoes ?? '';
        $this->showForm = true;
    }

    public function save(AssetStatusService $statusService, AssetMovementService $movementService): void
    {
        $companyId = ActiveOperatingCompany::id();

        $rules = [
            'codigo_patrimonio' => 'required|string|max:100|unique:assets,codigo_patrimonio'.($this->editingId ? ','.$this->editingId : ''),
            'equipment_model_id' => [
                'required',
                Rule::exists('equipment_models', 'id')->where(
                    fn ($query) => $companyId
                        ? $query->where('operating_company_id', $companyId)
                        : $query
                ),
            ],
            'serie' => 'nullable|string|max:255',
            'valor_compra' => 'nullable|numeric|min:0',
            'data_compra' => 'nullable|date',
            'localizacao' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ];

        if (! $this->editingId) {
            $rules['initial_status'] = 'required|in:disponivel,bloqueado';
            $rules['motivo_bloqueio'] = 'required_if:initial_status,bloqueado|nullable|string|max:1000';
        }

        $data = $this->validate($rules);

        $payload = [
            'codigo_patrimonio' => $data['codigo_patrimonio'],
            'equipment_model_id' => $data['equipment_model_id'],
            'serie' => $data['serie'] ?: null,
            'valor_compra' => $data['valor_compra'] ?: null,
            'data_compra' => $data['data_compra'] ?: null,
            'localizacao' => $data['localizacao'] ?: null,
            'observacoes' => $data['observacoes'] ?: null,
        ];

        if ($this->editingId) {
            $asset = Asset::findOrFail($this->editingId);
            $this->authorize('update', $asset);

            if (! auth()->user()->can('fleet.assets.manage')) {
                unset($payload['valor_compra']);
            }

            $novaLocalizacao = $payload['localizacao'] ?? null;
            $localizacaoAnterior = $asset->localizacao;
            unset($payload['localizacao']);

            $asset->update($payload);

            if ($novaLocalizacao !== null && $novaLocalizacao !== $localizacaoAnterior) {
                $movementService->moveLocation($asset->fresh(), $novaLocalizacao, 'Atualização via cadastro');
            }
        } else {
            $this->authorize('create', Asset::class);
            $asset = new Asset($payload);
            $initialStatus = AssetStatus::from($data['initial_status']);
            $statusService->createWithInitialStatus(
                $asset,
                $initialStatus,
                $data['motivo_bloqueio'] ?? null,
            );
        }

        $this->resetForm();
        session()->flash('success', 'Patrimônio salvo com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->codigo_patrimonio = '';
        $this->equipment_model_id = null;
        $this->serie = '';
        $this->valor_compra = null;
        $this->data_compra = null;
        $this->localizacao = '';
        $this->observacoes = '';
        $this->initial_status = 'disponivel';
        $this->motivo_bloqueio = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        $query = Asset::query()
            ->with(['equipmentModel.category'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->whereHas(
                'equipmentModel',
                fn ($q) => $q->where('equipment_category_id', $this->categoryFilter)
            ))
            ->orderBy('codigo_patrimonio');

        if (filled($this->search)) {
            $assets = $query->get()->filter(function (Asset $asset) {
                return TextSearch::matchesAny(
                    $this->search,
                    $asset->codigo_patrimonio,
                    $asset->serie,
                    $asset->localizacao,
                    $asset->equipmentModel?->marca,
                    $asset->equipmentModel?->modelo,
                    $asset->equipmentModel?->category?->nome,
                );
            });

            $page = $this->getPage();
            $perPage = 20;
            $items = $assets->slice(($page - 1) * $perPage, $perPage)->values();

            $assets = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $assets->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        } else {
            $assets = $query->paginate(20);
        }

        $models = EquipmentModel::with('category')->where('ativo', true)->orderBy('marca')->get();
        $categories = EquipmentCategory::where('ativo', true)->orderBy('nome')->get();
        $statuses = AssetStatus::cases();

        return view('livewire.fleet.asset-index', compact('assets', 'models', 'categories', 'statuses'));
    }
}
