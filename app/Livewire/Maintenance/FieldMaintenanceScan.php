<?php

namespace App\Livewire\Maintenance;

use App\Enums\MaintenanceOrderType;
use App\Enums\RentalStatus;
use App\Models\Domain\Fleet\Asset;
use App\Models\Domain\Maintenance\MaintenanceOrder;
use App\Models\Domain\Rental\Rental;
use App\Services\MaintenanceOrderService;
use App\Support\FlashMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile-yard')]
class FieldMaintenanceScan extends Component
{
    use AuthorizesRequests;

    public Asset $asset;

    public ?Rental $activeRental = null;

    public ?MaintenanceOrder $openFieldOrder = null;

    public string $mode = 'view';

    public string $descricao_problema = '';

    /** @var array<string, bool> */
    public array $checklist = [];

    public string $solucao = '';

    public string $horimetro = '';

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
            ->where('status', RentalStatus::Locado->value)
            ->latest('id')
            ->first();

        $this->openFieldOrder = MaintenanceOrder::query()
            ->where('asset_id', $this->asset->id)
            ->where('tipo', MaintenanceOrderType::Campo->value)
            ->open()
            ->latest('id')
            ->first();

        if ($this->openFieldOrder) {
            $this->mode = 'complete';
            $this->checklist = array_fill_keys(array_keys(MaintenanceOrderService::CHECKLIST_CAMPO), false);
            $this->descricao_problema = $this->openFieldOrder->descricao_problema ?? '';
        } elseif ($this->activeRental) {
            $this->mode = 'open';
        }
    }

    public function openOrder(MaintenanceOrderService $service): void
    {
        abort_unless(auth()->user()?->can('maintenance.operate'), 403);

        $data = $this->validate([
            'descricao_problema' => 'required|string|max:2000',
        ]);

        try {
            $order = $service->openField(
                $this->asset,
                $data['descricao_problema'],
                $this->activeRental,
            );
            FlashMessage::success("OS {$order->codigo} aberta em campo.");
            $this->redirectRoute('field.maintenance.scan', $this->asset->codigo_patrimonio, navigate: true);
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());
        }
    }

    public function completeOrder(MaintenanceOrderService $service): void
    {
        abort_unless(auth()->user()?->can('maintenance.operate'), 403);

        if (! $this->openFieldOrder) {
            return;
        }

        $this->validate([
            'solucao' => 'nullable|string|max:2000',
            'horimetro' => 'nullable|numeric|min:0',
        ]);

        try {
            $service->completeField(
                $this->openFieldOrder,
                $this->checklist,
                $this->solucao ?: null,
                $this->horimetro !== '' ? (float) $this->horimetro : null,
            );
            FlashMessage::success("OS {$this->openFieldOrder->codigo} concluída em campo.");
            $this->redirectRoute('field.maintenance.scan', $this->asset->codigo_patrimonio, navigate: true);
        } catch (\InvalidArgumentException $e) {
            FlashMessage::error($e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.maintenance.field-maintenance-scan', [
            'checklistLabels' => MaintenanceOrderService::CHECKLIST_CAMPO,
        ]);
    }
}
