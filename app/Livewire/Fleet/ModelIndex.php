<?php

namespace App\Livewire\Fleet;

use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Fleet\EquipmentModel;
use App\Support\TextSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ModelIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $equipment_category_id = null;

    public string $marca = '';

    public string $modelo = '';

    public string $especificacoes = '';

    public bool $ativo = true;

    public ?int $template_model_id = null;

    public bool $showInlineCategoryForm = false;

    public string $inline_category_nome = '';

    public string $inline_category_tipo_linha = 'linha_leve';

    public function mount(): void
    {
        $this->authorize('viewAny', EquipmentModel::class);

        if (request()->filled('search')) {
            $this->search = request()->string('search')->toString();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', EquipmentModel::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function updatedTemplateModelId(?int $value): void
    {
        if (! $value) {
            return;
        }

        $template = EquipmentModel::query()->find($value);

        if (! $template) {
            return;
        }

        $this->equipment_category_id = $template->equipment_category_id;
        $this->marca = $template->marca;
        $this->modelo = $template->modelo;
        $this->especificacoes = $template->especificacoes
            ? json_encode($template->especificacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }

    public function openInlineCategoryForm(): void
    {
        $this->authorize('create', EquipmentCategory::class);
        $this->inline_category_nome = '';
        $this->inline_category_tipo_linha = 'linha_leve';
        $this->showInlineCategoryForm = true;
    }

    public function saveInlineCategory(): void
    {
        $this->authorize('create', EquipmentCategory::class);

        $data = $this->validate([
            'inline_category_nome' => 'required|string|max:255',
            'inline_category_tipo_linha' => 'required|string|max:100',
        ], [], [
            'inline_category_nome' => 'nome da categoria',
            'inline_category_tipo_linha' => 'tipo de linha',
        ]);

        $category = EquipmentCategory::create([
            'nome' => $data['inline_category_nome'],
            'tipo_linha' => $data['inline_category_tipo_linha'],
            'ativo' => true,
        ]);

        $this->equipment_category_id = $category->id;
        $this->showInlineCategoryForm = false;
        $this->inline_category_nome = '';
        session()->flash('success', 'Categoria criada e selecionada.');
    }

    public function edit(int $id): void
    {
        $model = EquipmentModel::findOrFail($id);
        $this->authorize('update', $model);

        $this->editingId = $model->id;
        $this->equipment_category_id = $model->equipment_category_id;
        $this->marca = $model->marca;
        $this->modelo = $model->modelo;
        $this->especificacoes = $model->especificacoes ? json_encode($model->especificacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
        $this->ativo = $model->ativo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'equipment_category_id' => 'required|exists:equipment_categories,id',
            'marca' => 'required|string|max:255',
            'modelo' => 'required|string|max:255',
            'especificacoes' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $specs = null;
        if (filled($data['especificacoes'])) {
            $decoded = json_decode($data['especificacoes'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('especificacoes', 'JSON inválido nas especificações.');

                return;
            }
            $specs = $decoded;
        }

        $payload = [
            'equipment_category_id' => $data['equipment_category_id'],
            'marca' => $data['marca'],
            'modelo' => $data['modelo'],
            'especificacoes' => $specs,
            'ativo' => $data['ativo'],
        ];

        if ($this->editingId) {
            $model = EquipmentModel::findOrFail($this->editingId);
            $this->authorize('update', $model);
            $model->update($payload);
        } else {
            $this->authorize('create', EquipmentModel::class);
            EquipmentModel::create($payload);
        }

        $this->resetForm();
        session()->flash('success', 'Modelo salvo com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->equipment_category_id = null;
        $this->marca = '';
        $this->modelo = '';
        $this->especificacoes = '';
        $this->ativo = true;
        $this->template_model_id = null;
        $this->showInlineCategoryForm = false;
        $this->inline_category_nome = '';
        $this->inline_category_tipo_linha = 'linha_leve';
        $this->resetValidation();
    }

    public function render(): View
    {
        $query = EquipmentModel::query()
            ->with('category')
            ->when($this->categoryFilter, fn ($q) => $q->where('equipment_category_id', $this->categoryFilter))
            ->orderBy('marca')
            ->orderBy('modelo');

        if (filled($this->search)) {
            $models = $query->get()->filter(function (EquipmentModel $model) {
                return TextSearch::matchesAny(
                    $this->search,
                    $model->marca,
                    $model->modelo,
                    $model->category?->nome,
                );
            });

            $page = $this->getPage();
            $perPage = 20;
            $items = $models->slice(($page - 1) * $perPage, $perPage)->values();

            $models = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $models->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        } else {
            $models = $query->paginate(20);
        }

        $categories = EquipmentCategory::where('ativo', true)->orderBy('nome')->get();
        $templateModels = EquipmentModel::query()
            ->with('category')
            ->where('ativo', true)
            ->orderBy('marca')
            ->orderBy('modelo')
            ->get();
        $existingBrands = EquipmentModel::query()->distinct()->orderBy('marca')->pluck('marca');
        $existingModelNames = EquipmentModel::query()->distinct()->orderBy('modelo')->pluck('modelo');

        return view('livewire.fleet.model-index', compact(
            'models',
            'categories',
            'templateModels',
            'existingBrands',
            'existingModelNames',
        ));
    }
}
