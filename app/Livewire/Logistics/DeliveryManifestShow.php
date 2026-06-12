<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryManifest;
use App\Models\Domain\Logistics\DeliveryVehicle;
use App\Services\DeliveryManifestService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeliveryManifestShow extends Component
{
    use AuthorizesRequests;

    public DeliveryManifest $manifest;

    public ?int $delivery_driver_id = null;

    public ?int $delivery_vehicle_id = null;

    public function mount(DeliveryManifest $manifest): void
    {
        $this->authorize('view', $manifest);
        $this->manifest = $manifest->load([
            'stops.rental.customer',
            'stops.rental.asset.yard',
            'stops.proof',
            'driver',
            'vehicle',
        ]);
        $this->delivery_driver_id = $manifest->delivery_driver_id;
        $this->delivery_vehicle_id = $manifest->delivery_vehicle_id;
    }

    public function saveResources(DeliveryManifestService $service): void
    {
        $this->authorize('update', $this->manifest);

        $driver = $this->delivery_driver_id
            ? DeliveryDriver::findOrFail($this->delivery_driver_id)
            : null;
        $vehicle = $this->delivery_vehicle_id
            ? DeliveryVehicle::findOrFail($this->delivery_vehicle_id)
            : null;

        $this->manifest = $service->assignResources($this->manifest, $driver, $vehicle);
        session()->flash('success', 'Motorista e veículo atualizados.');
    }

    public function startRoute(DeliveryManifestService $service): void
    {
        $this->authorize('update', $this->manifest);

        try {
            $this->manifest = $service->startRoute($this->manifest->fresh());
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', 'Rota iniciada.');
        $this->redirectRoute('logistics.manifest.show', $this->manifest, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.logistics.delivery-manifest-show', [
            'drivers' => DeliveryDriver::query()->active()->orderBy('nome')->get(),
            'vehicles' => DeliveryVehicle::query()->active()->orderBy('placa')->get(),
            'canOperate' => auth()->user()->can('update', $this->manifest),
        ]);
    }
}
