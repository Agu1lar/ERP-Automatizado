<?php



namespace App\Livewire\Maintenance;



use App\Enums\MaintenanceOrderType;

use App\Enums\MaintenancePriority;

use App\Models\Domain\Fleet\Asset;

use App\Models\Domain\Fleet\EquipmentCategory;

use App\Models\Domain\Maintenance\MaintenanceOrder;

use App\Models\User;

use App\Services\MaintenanceOrderService;

use App\Support\MaintenanceOsBuilder;

use App\Support\MaintenancePanelQuery;

use Carbon\Carbon;

use Illuminate\Contracts\View\View;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Livewire\Attributes\Layout;

use Livewire\Attributes\Url;

use Livewire\Component;

use Livewire\WithPagination;



#[Layout('layouts.app')]

class MaintenanceOrderIndex extends Component

{

    use AuthorizesRequests, WithPagination;



    #[Url(as: 'aba')]

    public string $activeView = 'painel';



    public string $search = '';



    public string $statusFilter = '';



    public string $panelSearch = '';



    public string $panelCategoryId = '';



    public string $panelAssignedTo = '';



    public string $panelPrioridade = '';



    public string $panelTipo = '';



    public bool $panelOverdueOnly = false;



    public bool $showForm = false;



    public ?int $asset_id = null;



    public string $asset_search = '';



    /** @var list<array<string, mixed>> */

    public array $assetSuggestions = [];



    /** @var array<string, mixed>|null */

    public ?array $assetPreview = null;



    public ?string $assetResolveMessage = null;



    public string $tipo = MaintenanceOrderType::Corretiva->value;



    public string $prioridade = MaintenancePriority::Normal->value;



    public string $descricao_problema = '';



    public bool $impeditiva = true;



    public string $expected_completion_at = '';



    public ?int $assigned_to = null;



    public string $observacoes = '';



    public function mount(): void

    {

        $this->authorize('viewAny', MaintenanceOrder::class);

        if (! in_array($this->activeView, ['painel', 'lista'], true)) {

            $this->activeView = 'painel';

        }

    }



    public function updatedSearch(): void

    {

        $this->resetPage();

    }



    public function updatedStatusFilter(): void

    {

        $this->resetPage();

    }



    public function updatedAssetSearch(): void

    {

        $this->searchAssets();

    }



    public function openForm(): void

    {

        $this->authorize('create', MaintenanceOrder::class);

        $this->resetFormFields();

        $this->showForm = true;

    }



    public function pickAsset(int $id): void

    {

        $asset = Asset::with('equipmentModel.category')->findOrFail($id);

        $preview = MaintenanceOsBuilder::assetPreview($asset);



        $this->asset_id = $asset->id;

        $this->asset_search = $asset->codigo_patrimonio;

        $this->assetSuggestions = [];

        $this->assetPreview = $preview;

        $this->assetResolveMessage = $preview['has_open_os']

            ? 'Este patrimônio já possui OS aberta — não é possível abrir outra.'

            : null;

    }



    public function save(MaintenanceOrderService $service): void

    {

        $this->authorize('create', MaintenanceOrder::class);



        if (! $this->asset_id && filled($this->asset_search)) {

            $this->searchAssets();

            if (count($this->assetSuggestions) === 1) {

                $this->pickAsset($this->assetSuggestions[0]['id']);

            }

        }



        $data = $this->validate([

            'asset_id' => 'required|exists:assets,id',

            'tipo' => 'required|string',

            'prioridade' => 'required|string',

            'descricao_problema' => 'required|string|max:5000',

            'impeditiva' => 'boolean',

            'expected_completion_at' => 'nullable|date',

            'assigned_to' => 'nullable|exists:users,id',

            'observacoes' => 'nullable|string|max:2000',

        ]);



        $asset = Asset::findOrFail($data['asset_id']);



        try {

            $order = $service->open(

                $asset,

                $data['descricao_problema'],

                MaintenanceOrderType::from($data['tipo']),

                MaintenancePriority::from($data['prioridade']),

                $data['impeditiva'],

                $data['expected_completion_at'] ? Carbon::parse($data['expected_completion_at']) : null,

                $data['assigned_to'],

                null,

                $data['observacoes'] ?: null,

            );

        } catch (\InvalidArgumentException $e) {

            $this->addError('asset_id', $e->getMessage());



            return;

        }



        $this->resetFormFields();

        session()->flash('success', "OS {$order->codigo} aberta — dados do patrimônio vinculados.");



        $this->redirect(route('maintenance.show', $order), navigate: true);

    }



    public function cancelForm(): void

    {

        $this->resetFormFields();

    }



    private function searchAssets(): void

    {

        $term = trim($this->asset_search);

        $this->asset_id = null;

        $this->assetPreview = null;

        $this->assetResolveMessage = null;

        $this->assetSuggestions = [];



        if ($term === '') {

            return;

        }



        $matches = Asset::query()

            ->with('equipmentModel.category')

            ->where(function ($query) use ($term) {

                $query->where('codigo_patrimonio', 'like', '%'.$term.'%')

                    ->orWhere('serie', 'like', '%'.$term.'%');

            })

            ->orderByRaw('CASE WHEN codigo_patrimonio = ? THEN 0 WHEN codigo_patrimonio LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])

            ->limit(8)

            ->get();



        $exact = $matches->firstWhere('codigo_patrimonio', $term);

        if ($exact) {

            $this->pickAsset($exact->id);



            return;

        }



        if ($matches->count() === 1) {

            $this->pickAsset($matches->first()->id);



            return;

        }



        if ($matches->isEmpty()) {

            $this->assetResolveMessage = 'Nenhum patrimônio encontrado.';



            return;

        }



        $this->assetSuggestions = $matches->map(function (Asset $asset) {

            $preview = MaintenanceOsBuilder::assetPreview($asset);



            return [

                'id' => $asset->id,

                'codigo' => $asset->codigo_patrimonio,

                'modelo' => $asset->equipmentDisplayName(),

                'status' => $preview['status'],

                'has_open_os' => $preview['has_open_os'],

            ];

        })->all();

    }



    private function resetFormFields(): void

    {

        $this->showForm = false;

        $this->asset_id = null;

        $this->asset_search = '';

        $this->assetSuggestions = [];

        $this->assetPreview = null;

        $this->assetResolveMessage = null;

        $this->tipo = MaintenanceOrderType::Corretiva->value;

        $this->prioridade = MaintenancePriority::Normal->value;

        $this->descricao_problema = '';

        $this->impeditiva = true;

        $this->expected_completion_at = '';

        $this->assigned_to = null;

        $this->observacoes = '';

        $this->resetValidation();

    }



    public function render(): View

    {

        $orders = MaintenanceOrder::query()

            ->with(['asset.equipmentModel', 'assignedToUser'])

            ->when($this->search, function ($query) {

                $term = '%'.$this->search.'%';

                $query->where(function ($q) use ($term) {

                    $q->where('codigo', 'like', $term)

                        ->orWhereHas('asset', fn ($aq) => $aq->where('codigo_patrimonio', 'like', $term));

                });

            })

            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))

            ->latest('opened_at')

            ->paginate(20);



        $technicians = User::query()->where('ativo', true)->orderBy('name')->get();



        $panelFilters = [

            'search' => $this->panelSearch,

            'category_id' => $this->panelCategoryId !== '' ? $this->panelCategoryId : null,

            'assigned_to' => $this->panelAssignedTo !== '' ? $this->panelAssignedTo : null,

            'prioridade' => $this->panelPrioridade !== '' ? $this->panelPrioridade : null,

            'tipo' => $this->panelTipo !== '' ? $this->panelTipo : null,

            'overdue_only' => $this->panelOverdueOnly,

        ];

        $panelQuery = app(MaintenancePanelQuery::class);

        return view('livewire.maintenance.maintenance-order-index', [

            'orders' => $orders,

            'technicians' => $technicians,

            'statusOptions' => \App\Enums\MaintenanceOrderStatus::cases(),

            'typeOptions' => MaintenanceOrderType::cases(),

            'priorityOptions' => MaintenancePriority::cases(),

            'boardColumns' => $panelQuery->boardColumns($panelFilters),

            'categories' => EquipmentCategory::query()->where('ativo', true)->orderBy('nome')->get(),

            'overdueOrdersCount' => MaintenanceOrder::query()->overdue()->count(),

        ]);

    }

}


