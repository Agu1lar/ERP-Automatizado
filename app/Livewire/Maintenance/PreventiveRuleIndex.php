<?php

namespace App\Livewire\Maintenance;

use App\Models\Domain\Fleet\EquipmentModel;
use App\Models\Domain\Maintenance\PreventiveMaintenanceRule;
use App\Services\PreventiveMaintenanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PreventiveRuleIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $equipment_model_id = '';

    public string $interval_horas = '';

    public string $descricao = '';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', PreventiveMaintenanceRule::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', PreventiveMaintenanceRule::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $rule = PreventiveMaintenanceRule::findOrFail($id);
        $this->authorize('update', $rule);

        $this->editingId = $rule->id;
        $this->equipment_model_id = (string) $rule->equipment_model_id;
        $this->interval_horas = (string) $rule->interval_horas;
        $this->descricao = $rule->descricao;
        $this->ativo = $rule->ativo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'equipment_model_id' => 'required|exists:equipment_models,id',
            'interval_horas' => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:500',
            'ativo' => 'boolean',
        ]);

        $payload = [
            'equipment_model_id' => (int) $data['equipment_model_id'],
            'interval_horas' => $data['interval_horas'],
            'descricao' => $data['descricao'],
            'ativo' => $data['ativo'],
        ];

        if ($this->editingId) {
            $rule = PreventiveMaintenanceRule::findOrFail($this->editingId);
            $this->authorize('update', $rule);
            $rule->update($payload);
        } else {
            $this->authorize('create', PreventiveMaintenanceRule::class);
            app(PreventiveMaintenanceService::class)->createRule(
                $payload['equipment_model_id'],
                (float) $payload['interval_horas'],
                $payload['descricao'],
            );
        }

        $this->resetForm();
        session()->flash('success', 'Regra de manutenção preventiva salva.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        $query = PreventiveMaintenanceRule::query()
            ->with('equipmentModel.category')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('descricao', 'like', $term)
                        ->orWhereHas('equipmentModel', fn ($m) => $m->where('marca', 'like', $term)->orWhere('modelo', 'like', $term));
                });
            })
            ->latest();

        $preventiveService = app(PreventiveMaintenanceService::class);

        return view('livewire.maintenance.preventive-rule-index', [
            'rules' => $query->paginate(15),
            'models' => EquipmentModel::query()->with('category')->where('ativo', true)->orderBy('marca')->get(),
            'dueCount' => $preventiveService->countDueAssets(),
            'upcomingCount' => $preventiveService->countUpcomingAssets(),
            'autoMode' => config('maintenance.preventive_auto_mode', 'open_when_available'),
            'warningHours' => $preventiveService->warningHours(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->equipment_model_id = '';
        $this->interval_horas = '';
        $this->descricao = '';
        $this->ativo = true;
        $this->resetValidation();
    }
}
