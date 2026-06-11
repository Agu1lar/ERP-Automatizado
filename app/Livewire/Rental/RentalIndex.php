<?php

namespace App\Livewire\Rental;

use App\Enums\RentalPricingPeriod;
use App\Enums\RentalStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Fleet\EquipmentCategory;
use App\Models\Domain\Rental\Rental;
use App\Rules\ValidCpfCnpj;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use App\Support\FlashMessage;
use App\Support\WorkflowNextStep;
use App\Support\ActiveOperatingCompany;
use App\Support\RentalFichaBuilder;
use App\Support\RentalPanelQuery;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RentalIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'aba')]
    public string $activeView = 'painel';

    public string $search = '';

    public string $statusFilter = '';

    public string $panelSearch = '';

    public string $panelStatusScope = 'locado';

    public string $panelCategoryId = '';

    public string $panelCustomerId = '';

    public string $panelCustomerSearch = '';

    public string $panelValorMin = '';

    public string $panelValorMax = '';

    public string $panelSortBy = 'retorno';

    public string $panelSortDir = 'asc';

    public bool $showCustomerHistory = false;

    #[Url(as: 'atrasados')]
    public bool $panelOverdueOnly = false;

    /** @var list<array<string, mixed>> */
    public array $panelCustomerSuggestions = [];

    public bool $showReserveForm = false;

    public ?int $asset_id = null;

    public ?int $customer_id = null;

    public string $expected_return_at = '';

    public string $pricing_period = '';

    public string $observacoes = '';

    public string $local_obra = '';

    public string $asset_search = '';

    public string $customer_search = '';

    /** @var list<array<string, mixed>> */
    public array $assetSuggestions = [];

    /** @var list<array<string, mixed>> */
    public array $customerSuggestions = [];

    /** @var array<string, mixed>|null */
    public ?array $assetPreview = null;

    public ?string $assetResolveMessage = null;

    public bool $showQuickCustomer = false;

    public string $quick_customer_nome = '';

    public string $quick_customer_cpf_cnpj = '';

    public string $quick_customer_telefone = '';

    public string $quick_customer_email = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rental::class);

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

    public function updatedPanelSearch(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelStatusScope(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelCategoryId(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelCustomerId(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelValorMin(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelValorMax(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelSortBy(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelSortDir(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedShowCustomerHistory(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelOverdueOnly(): void
    {
        $this->resetPage('panelPage');
    }

    public function updatedPanelCustomerSearch(): void
    {
        $this->searchPanelCustomers();
    }

    public function updatedAssetSearch(): void
    {
        $this->searchAssets();
    }

    public function updatedCustomerSearch(): void
    {
        $this->searchCustomers();
    }

    public function pickPanelCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->panelCustomerId = (string) $customer->id;
        $this->panelCustomerSearch = $customer->nome;
        $this->panelCustomerSuggestions = [];
        $this->resetPage('panelPage');
    }

    public function clearPanelCustomer(): void
    {
        $this->panelCustomerId = '';
        $this->panelCustomerSearch = '';
        $this->panelCustomerSuggestions = [];
        $this->showCustomerHistory = false;
        $this->resetPage('panelPage');
    }

    public function openReserveForm(): void
    {
        $this->authorize('reserve', Rental::class);
        $this->resetReserveForm();
        $this->showReserveForm = true;
        $this->activeView = 'lista';
    }

    public function updatedExpectedReturnAt(): void
    {
        // Re-render triggers price estimate refresh.
    }

    public function updatedPricingPeriod(): void
    {
        // Re-render triggers price estimate refresh.
    }

    public function pickAsset(int $id): void
    {
        $asset = Asset::with('equipmentModel.category')->findOrFail($id);
        $this->asset_id = $asset->id;
        $this->asset_search = $asset->codigo_patrimonio;
        $this->assetSuggestions = [];
        $this->assetResolveMessage = $asset->isAvailableForRental()
            ? null
            : "Patrimônio encontrado, mas está {$asset->statusEnum()->label()} — não pode ser reservado agora.";
        $this->assetPreview = RentalFichaBuilder::assetPreview($asset);
    }

    public function pickCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->customer_id = $customer->id;
        $this->customer_search = $customer->nome;
        $this->customerSuggestions = [];
        $this->showQuickCustomer = false;

        if (blank($this->local_obra)) {
            $this->local_obra = RentalFichaBuilder::prefillLocalObraFromCustomer($customer) ?? '';
        }
    }

    public function createQuickCustomer(): void
    {
        $this->authorize('create', Customer::class);

        $data = $this->validate([
            'quick_customer_nome' => 'required|string|max:255',
            'quick_customer_cpf_cnpj' => ['required', 'string', 'max:20', 'unique:customers,cpf_cnpj', new ValidCpfCnpj],
            'quick_customer_telefone' => 'nullable|string|max:30',
            'quick_customer_email' => 'nullable|email|max:255',
        ]);

        $customer = Customer::create([
            'nome' => $data['quick_customer_nome'],
            'cpf_cnpj' => preg_replace('/\D/', '', $data['quick_customer_cpf_cnpj']),
            'telefone' => $data['quick_customer_telefone'] ?: null,
            'email' => $data['quick_customer_email'] ?: null,
            'ativo' => true,
            'created_by' => auth()->id(),
        ]);

        $this->pickCustomer($customer->id);
        $this->quick_customer_nome = '';
        $this->quick_customer_cpf_cnpj = '';
        $this->quick_customer_telefone = '';
        $this->quick_customer_email = '';
        $this->showQuickCustomer = false;
        session()->flash('success', "Cliente {$customer->nome} cadastrado.");
    }

    public function saveReservation(RentalService $rentalService): void
    {
        $this->authorize('reserve', Rental::class);

        if (! $this->asset_id && filled($this->asset_search)) {
            $this->searchAssets();
            if (count($this->assetSuggestions) === 1) {
                $this->pickAsset($this->assetSuggestions[0]['id']);
            }
        }

        if (! $this->customer_id && filled($this->customer_search)) {
            $this->searchCustomers();
            if (count($this->customerSuggestions) === 1) {
                $this->pickCustomer($this->customerSuggestions[0]['id']);
            }
        }

        $data = $this->validate([
            'asset_id' => 'required|exists:assets,id',
            'customer_id' => 'required|exists:customers,id',
            'expected_return_at' => 'nullable|date|after_or_equal:today',
            'observacoes' => 'nullable|string|max:2000',
            'local_obra' => 'nullable|string|max:2000',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        $customer = Customer::findOrFail($data['customer_id']);

        $period = filled($this->pricing_period)
            ? RentalPricingPeriod::from($this->pricing_period)
            : null;

        try {
            $rental = $rentalService->reserve(
                $asset,
                $customer,
                $data['expected_return_at'] ? Carbon::parse($data['expected_return_at']) : null,
                $data['observacoes'] ?: null,
                null,
                $data['local_obra'] ?: null,
                $period,
            );
        } catch (\InvalidArgumentException $e) {
            if ($this->isCustomerRentalBlockError($customer, $e->getMessage())) {
                FlashMessage::error($e->getMessage(), WorkflowNextStep::customerBlocked($customer));

                return;
            }

            $this->addError('asset_id', $e->getMessage());

            return;
        }

        $this->resetReserveForm();
        FlashMessage::success(
            "Reserva {$rental->codigo} criada — ficha preenchida com dados do patrimônio.",
            WorkflowNextStep::rentalAfterReserve($rental),
        );

        $this->redirect(route('rentals.show', $rental), navigate: true);
    }

    public function cancelReserve(): void
    {
        $this->resetReserveForm();
    }

    public function render(): View
    {
        $panelQuery = app(RentalPanelQuery::class);
        $panelFilters = [
            'search' => $this->panelSearch,
            'status_scope' => $this->panelStatusScope,
            'category_id' => $this->panelCategoryId !== '' ? $this->panelCategoryId : null,
            'customer_id' => $this->panelCustomerId !== '' ? $this->panelCustomerId : null,
            'valor_min' => $this->panelValorMin,
            'valor_max' => $this->panelValorMax,
            'sort_by' => $this->panelSortBy,
            'sort_dir' => $this->panelSortDir,
            'show_customer_history' => $this->showCustomerHistory,
            'overdue_only' => $this->panelOverdueOnly,
        ];

        $panelRentals = $panelQuery->apply($panelFilters)->paginate(20, ['*'], 'panelPage');

        $rentals = Rental::query()
            ->with(['asset.equipmentModel', 'customer'])
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', $term)
                        ->orWhereHas('asset', fn ($aq) => $aq->where('codigo_patrimonio', 'like', $term))
                        ->orWhereHas('customer', fn ($cq) => $cq->where('nome', 'like', $term));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        $selectedPanelCustomer = $this->panelCustomerId !== ''
            ? Customer::find($this->panelCustomerId)
            : null;

        $customerHistorySummary = $selectedPanelCustomer && $this->showCustomerHistory
            ? $panelQuery->summaryForCustomer($selectedPanelCustomer->id)
            : collect();

        $prefillPreview = $this->assetPreview
            ? RentalFichaBuilder::prefillFromAsset(Asset::with('equipmentModel.category')->find($this->asset_id))
            : null;

        $priceEstimate = null;
        if ($this->asset_id && filled($this->expected_return_at)) {
            $asset = Asset::with('equipmentModel.category')->find($this->asset_id);
            if ($asset) {
                $period = filled($this->pricing_period)
                    ? RentalPricingPeriod::from($this->pricing_period)
                    : null;
                $priceEstimate = app(RentalPricingService::class)->calculate(
                    $asset,
                    now(),
                    Carbon::parse($this->expected_return_at),
                    $period,
                );
            }
        }

        return view('livewire.rental.rental-index', [
            'rentals' => $rentals,
            'panelRentals' => $panelRentals,
            'statusOptions' => RentalStatus::cases(),
            'categories' => EquipmentCategory::query()->where('ativo', true)->orderBy('nome')->get(),
            'sortOptions' => RentalPanelQuery::sortOptions(),
            'selectedPanelCustomer' => $selectedPanelCustomer,
            'customerHistorySummary' => $customerHistorySummary,
            'prefillPreview' => $prefillPreview,
            'canCreateCustomer' => auth()->user()->can('create', Customer::class),
            'overdueReturnsCount' => Rental::query()->overdueReturns()->count(),
            'priceEstimate' => $priceEstimate,
            'pricingPeriodOptions' => RentalPricingPeriod::cases(),
            'activeCompany' => ActiveOperatingCompany::current(),
        ]);
    }

    private function searchPanelCustomers(): void
    {
        $term = trim($this->panelCustomerSearch);
        $this->panelCustomerId = '';
        $this->panelCustomerSuggestions = [];
        $this->showCustomerHistory = false;

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        $matches = Customer::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $digits) {
                $query->where('nome', 'like', '%'.$term.'%');
                if ($digits !== '') {
                    $query->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit(8)
            ->get();

        if ($matches->count() === 1 && strlen($term) >= 3) {
            $this->pickPanelCustomer($matches->first()->id);

            return;
        }

        $this->panelCustomerSuggestions = $matches->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'nome' => $customer->nome,
            'documento' => $customer->formattedDocument(),
        ])->all();
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
            $this->assetResolveMessage = 'Nenhum patrimônio encontrado com esse código ou série.';

            return;
        }

        $this->assetSuggestions = $matches->map(fn (Asset $asset) => [
            'id' => $asset->id,
            'codigo' => $asset->codigo_patrimonio,
            'modelo' => $asset->equipmentDisplayName(),
            'status' => $asset->statusEnum()->label(),
            'disponivel' => $asset->isAvailableForRental(),
        ])->all();
    }

    private function searchCustomers(): void
    {
        $term = trim($this->customer_search);
        $this->customer_id = null;
        $this->customerSuggestions = [];

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        $matches = Customer::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $digits) {
                $query->where('nome', 'like', '%'.$term.'%');
                if ($digits !== '') {
                    $query->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome')
            ->limit(8)
            ->get();

        if ($digits !== '' && $matches->count() === 1) {
            $this->pickCustomer($matches->first()->id);

            return;
        }

        if ($matches->count() === 1 && strlen($term) >= 3) {
            $this->pickCustomer($matches->first()->id);

            return;
        }

        $this->customerSuggestions = $matches->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'nome' => $customer->nome,
            'documento' => $customer->formattedDocument(),
            'telefone' => $customer->telefone,
        ])->all();
    }

    private function isCustomerRentalBlockError(Customer $customer, string $message): bool
    {
        if ($customer->isManuallyBlocked() || $customer->hasOverdueTitles()) {
            return true;
        }

        $lower = mb_strtolower($message);

        return str_contains($lower, 'bloqueado')
            || str_contains($lower, 'crédito')
            || str_contains($lower, 'inadimpl')
            || str_contains($lower, 'atraso');
    }

    private function resetReserveForm(): void
    {
        $this->showReserveForm = false;
        $this->asset_id = null;
        $this->customer_id = null;
        $this->expected_return_at = '';
        $this->pricing_period = '';
        $this->observacoes = '';
        $this->local_obra = '';
        $this->asset_search = '';
        $this->customer_search = '';
        $this->assetSuggestions = [];
        $this->customerSuggestions = [];
        $this->assetPreview = null;
        $this->assetResolveMessage = null;
        $this->showQuickCustomer = false;
        $this->quick_customer_nome = '';
        $this->quick_customer_cpf_cnpj = '';
        $this->quick_customer_telefone = '';
        $this->quick_customer_email = '';
        $this->resetValidation();
    }
}
