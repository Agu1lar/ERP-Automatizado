<?php

namespace App\Livewire\Pricing;

use App\Enums\RentalPricingPeriod;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Fleet\EquipmentPricing;
use App\Services\EquipmentPricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PricingIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $viewMode = 'categories';

    public string $categorySearch = '';

    /** @var array<int, array<string, string>> */
    public array $categoryGrid = [];

    public string $search = '';

    public string $targetFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $target_type = 'model';

    public ?int $equipment_model_id = null;

    public ?int $equipment_category_id = null;

    public string $periodo = RentalPricingPeriod::Diaria->value;

    public string $valor = '';

    public bool $ativo = true;

    public function mount(EquipmentPricingService $pricingService): void
    {
        $this->authorize('viewAny', EquipmentPricing::class);
        $this->loadCategoryGrid($pricingService);
    }

    public function updatedCategorySearch(): void
    {
        //
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTargetFilter(): void
    {
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['categories', 'advanced'], true)) {
            return;
        }

        $this->viewMode = $mode;

        if ($mode === 'categories') {
            $this->loadCategoryGrid(app(EquipmentPricingService::class));
        }
    }

    public function saveCategoryRow(int $categoryId, EquipmentPricingService $pricingService): void
    {
        $this->authorize('create', EquipmentPricing::class);

        try {
            $pricingService->syncCategoryPrices($categoryId, $this->categoryGrid[$categoryId] ?? []);
        } catch (InvalidArgumentException $exception) {
            $this->addError('categoryGrid.'.$categoryId, $exception->getMessage());

            return;
        }

        $this->loadCategoryGrid($pricingService);
        session()->flash('success', 'Preços da categoria atualizados.');
    }

    public function saveAllCategoryPrices(EquipmentPricingService $pricingService): void
    {
        $this->authorize('create', EquipmentPricing::class);

        try {
            $pricingService->syncAllCategoryPrices($this->categoryGrid);
        } catch (InvalidArgumentException $exception) {
            $this->addError('categoryGrid', $exception->getMessage());

            return;
        }

        $this->loadCategoryGrid($pricingService);
        session()->flash('success', 'Tabela de preços por categoria salva.');
    }

    public function create(): void
    {
        $this->authorize('create', EquipmentPricing::class);
        $this->resetForm();
        $this->showForm = true;
        $this->viewMode = 'advanced';
    }

    public function edit(int $id): void
    {
        $pricing = EquipmentPricing::findOrFail($id);
        $this->authorize('update', $pricing);

        $this->editingId = $pricing->id;
        $this->target_type = $pricing->equipment_model_id ? 'model' : 'category';
        $this->equipment_model_id = $pricing->equipment_model_id;
        $this->equipment_category_id = $pricing->equipment_category_id;
        $this->periodo = $pricing->periodo;
        $this->valor = (string) $pricing->valor;
        $this->ativo = $pricing->ativo;
        $this->showForm = true;
        $this->viewMode = 'advanced';
    }

    public function save(EquipmentPricingService $pricingService): void
    {
        $rules = [
            'target_type' => 'required|in:model,category',
            'periodo' => 'required|in:diaria,semanal,mensal',
            'valor' => 'required|numeric|min:0',
            'ativo' => 'boolean',
        ];

        if ($this->target_type === 'model') {
            $rules['equipment_model_id'] = 'required|exists:equipment_models,id';
        } else {
            $rules['equipment_category_id'] = 'required|exists:equipment_categories,id';
        }

        $data = $this->validate($rules);

        $payload = [
            'equipment_model_id' => $data['target_type'] === 'model' ? $data['equipment_model_id'] : null,
            'equipment_category_id' => $data['target_type'] === 'category' ? $data['equipment_category_id'] : null,
            'periodo' => $data['periodo'],
            'valor' => $data['valor'],
            'ativo' => $data['ativo'],
        ];

        if ($this->editingId) {
            $pricing = EquipmentPricing::findOrFail($this->editingId);
            $this->authorize('update', $pricing);
            $pricing->update($payload);
        } else {
            $this->authorize('create', EquipmentPricing::class);
            EquipmentPricing::create($payload);
        }

        $this->resetForm();
        $this->loadCategoryGrid($pricingService);
        session()->flash('success', 'Preço salvo com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(EquipmentPricingService $pricingService): View
    {
        $pricings = EquipmentPricing::query()
            ->with(['equipmentModel.category', 'category'])
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('equipmentModel', fn ($q) => $q->where('marca', 'like', $term)->orWhere('modelo', 'like', $term))
                        ->orWhereHas('category', fn ($q) => $q->where('nome', 'like', $term));
                });
            })
            ->when($this->targetFilter === 'model', fn ($q) => $q->whereNotNull('equipment_model_id'))
            ->when($this->targetFilter === 'category', fn ($q) => $q->whereNotNull('equipment_category_id'))
            ->orderByDesc('ativo')
            ->orderBy('periodo')
            ->paginate(25);

        $categoryRows = $pricingService->categoryGrid()
            ->when($this->categorySearch, function ($rows) {
                $term = mb_strtolower($this->categorySearch);

                return $rows->filter(
                    fn (array $row) => str_contains(mb_strtolower($row['category']->nome), $term)
                );
            })
            ->values();

        return view('livewire.pricing.pricing-index', [
            'pricings' => $pricings,
            'categoryRows' => $categoryRows,
            'periodOptions' => RentalPricingPeriod::cases(),
            'models' => EquipmentModel::query()->with('category')->where('ativo', true)->orderBy('marca')->get(),
            'categories' => EquipmentCategory::query()->where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    private function loadCategoryGrid(EquipmentPricingService $pricingService): void
    {
        $this->categoryGrid = $pricingService->categoryGrid()
            ->mapWithKeys(fn (array $row) => [$row['category']->id => $row['prices']])
            ->all();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->target_type = 'model';
        $this->equipment_model_id = null;
        $this->equipment_category_id = null;
        $this->periodo = RentalPricingPeriod::Diaria->value;
        $this->valor = '';
        $this->ativo = true;
        $this->resetValidation();
    }
}
