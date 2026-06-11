<?php

namespace App\Livewire\Rental;

use App\Enums\RentalPricingPeriod;
use App\Enums\RentalQuoteStatus;
use App\Models\Domain\Customer\Customer;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\RentalQuote;
use App\Services\RentalQuoteService;
use App\Support\FlashMessage;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class QuoteIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $asset_id = null;

    public ?int $customer_id = null;

    public string $expected_return_at = '';

    public string $local_obra = '';

    public string $observacoes = '';

    public string $pricing_period = '';

    public string $asset_search = '';

    public string $customer_search = '';

    public int $validity_days = 7;

    public function mount(): void
    {
        $this->authorize('viewAny', RentalQuote::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openForm(): void
    {
        $this->authorize('create', RentalQuote::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(RentalQuoteService $service): void
    {
        $this->authorize('create', RentalQuote::class);

        $this->validate([
            'asset_id' => 'required|exists:assets,id',
            'customer_id' => 'required|exists:customers,id',
            'expected_return_at' => 'nullable|date|after_or_equal:today',
            'local_obra' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:2000',
            'pricing_period' => 'nullable|in:'.implode(',', array_column(RentalPricingPeriod::cases(), 'value')),
        ]);

        $asset = Asset::findOrFail($this->asset_id);
        $customer = Customer::findOrFail($this->customer_id);
        $period = filled($this->pricing_period) ? RentalPricingPeriod::from($this->pricing_period) : null;
        $expected = filled($this->expected_return_at) ? Carbon::parse($this->expected_return_at) : null;

        $quote = $service->create($asset, $customer, $expected, $this->local_obra ?: null, $this->observacoes ?: null, $period);
        $quote = $service->send($quote, $this->validity_days);

        $this->showForm = false;
        FlashMessage::success("Orçamento {$quote->codigo} enviado — válido até {$quote->valid_until->format('d/m/Y')}.");
    }

    public function convert(int $id, RentalQuoteService $service): void
    {
        $quote = RentalQuote::findOrFail($id);
        $this->authorize('convert', $quote);

        try {
            $rental = $service->convertToReservation($quote);
            FlashMessage::success("Orçamento convertido em reserva {$rental->codigo}.", [
                ['label' => 'Abrir locação', 'url' => route('rentals.show', $rental), 'primary' => true],
            ]);
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());
        }
    }

    public function cancel(int $id, RentalQuoteService $service): void
    {
        $quote = RentalQuote::findOrFail($id);
        $this->authorize('cancel', $quote);

        $service->cancel($quote);
        FlashMessage::success("Orçamento {$quote->codigo} cancelado.");
    }

    public function render(): View
    {
        $quotes = RentalQuote::query()
            ->with(['asset.equipmentModel', 'customer', 'rental'])
            ->when($this->search, function ($q) {
                $term = $this->search;
                $q->where(function ($q) use ($term) {
                    $q->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('nome', 'like', '%'.$term.'%'))
                        ->orWhereHas('asset', fn ($a) => $a->where('codigo_patrimonio', 'like', '%'.$term.'%'));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->paginate(20);

        $assets = filled($this->asset_search)
            ? Asset::query()
                ->where('codigo_patrimonio', 'like', '%'.$this->asset_search.'%')
                ->orWhereHas('equipmentModel', fn ($q) => $q->where('modelo', 'like', '%'.$this->asset_search.'%'))
                ->limit(8)->get()
            : collect();

        $customers = filled($this->customer_search)
            ? Customer::query()
                ->where('nome', 'like', '%'.$this->customer_search.'%')
                ->limit(8)->get()
            : collect();

        return view('livewire.rental.quote-index', [
            'quotes' => $quotes,
            'statusOptions' => RentalQuoteStatus::cases(),
            'periodOptions' => RentalPricingPeriod::cases(),
            'assetSuggestions' => $assets,
            'customerSuggestions' => $customers,
        ]);
    }

    private function resetForm(): void
    {
        $this->asset_id = null;
        $this->customer_id = null;
        $this->expected_return_at = '';
        $this->local_obra = '';
        $this->observacoes = '';
        $this->pricing_period = '';
        $this->asset_search = '';
        $this->customer_search = '';
        $this->validity_days = 7;
        $this->resetValidation();
    }
}
