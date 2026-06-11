<?php

namespace App\Livewire\Yard;

use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Rental\Rental;
use App\Services\RentalService;
use App\Support\FlashMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile-yard')]
class AssetYardScan extends Component
{
    use AuthorizesRequests;

    public Asset $asset;

    public ?Rental $activeRental = null;

    /** @var array<string, bool> */
    public array $checklist = [];

    public string $observacoes = '';

    public string $mode = 'view';

    public function mount(string $codigo): void
    {
        $this->asset = Asset::query()
            ->with(['equipmentModel.category'])
            ->where('codigo_patrimonio', $codigo)
            ->firstOrFail();

        $this->authorize('view', $this->asset);

        $this->activeRental = Rental::query()
            ->with('customer')
            ->where('asset_id', $this->asset->id)
            ->whereIn('status', [
                RentalStatus::Reservado->value,
                RentalStatus::Locado->value,
            ])
            ->latest('id')
            ->first();

        if ($this->activeRental?->statusEnum() === RentalStatus::Reservado) {
            $this->mode = 'checkout';
            $this->checklist = array_fill_keys(array_keys(RentalService::CHECKLIST_SAIDA), false);
        } elseif ($this->activeRental?->statusEnum() === RentalStatus::Locado) {
            $this->mode = 'return';
            $this->checklist = array_fill_keys(array_keys(RentalService::CHECKLIST_RETORNO), false);
        }
    }

    public function submitCheckout(RentalService $service): void
    {
        abort_unless(auth()->user()?->can('rentals.operate'), 403);

        if (! $this->activeRental) {
            return;
        }

        try {
            $rental = $service->checkout(
                $this->activeRental,
                $this->checklist,
                $this->observacoes ?: null,
            );

            FlashMessage::success("Saída registrada — {$rental->codigo}.");
            $this->redirectRoute('yard.scan', $this->asset->codigo_patrimonio, navigate: true);
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());
        }
    }

    public function submitReturn(RentalService $service): void
    {
        abort_unless(auth()->user()?->can('rentals.operate'), 403);

        if (! $this->activeRental) {
            return;
        }

        try {
            $rental = $service->registerReturn(
                $this->activeRental,
                $this->checklist,
                $this->observacoes ?: null,
            );

            FlashMessage::success("Retorno registrado — {$rental->codigo}.");
            $this->redirectRoute('yard.scan', $this->asset->codigo_patrimonio, navigate: true);
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());
        }
    }

    public function render(): View
    {
        $checklistLabels = match ($this->mode) {
            'checkout' => RentalService::CHECKLIST_SAIDA,
            'return' => RentalService::CHECKLIST_RETORNO,
            default => [],
        };

        return view('livewire.yard.asset-yard-scan', [
            'checklistLabels' => $checklistLabels,
        ]);
    }
}
