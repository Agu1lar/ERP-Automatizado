<?php

namespace App\Livewire\Logistics;

use App\Models\Domain\Logistics\DeliveryDriver;
use App\Models\Domain\Logistics\DeliveryVehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LogisticsFleetIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'motoristas';

    public bool $showDriverForm = false;

    public ?int $editingDriverId = null;

    public string $driver_nome = '';

    public string $driver_cnh = '';

    public string $driver_telefone = '';

    public bool $driver_ativo = true;

    public bool $showVehicleForm = false;

    public ?int $editingVehicleId = null;

    public string $vehicle_placa = '';

    public string $vehicle_descricao = '';

    public string $vehicle_observacoes = '';

    public bool $vehicle_ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', DeliveryDriver::class);
    }

    public function createDriver(): void
    {
        $this->authorize('create', DeliveryDriver::class);
        $this->resetDriverForm();
        $this->showDriverForm = true;
    }

    public function editDriver(int $id): void
    {
        $driver = DeliveryDriver::findOrFail($id);
        $this->authorize('update', $driver);
        $this->editingDriverId = $driver->id;
        $this->driver_nome = $driver->nome;
        $this->driver_cnh = $driver->cnh ?? '';
        $this->driver_telefone = $driver->telefone ?? '';
        $this->driver_ativo = $driver->ativo;
        $this->showDriverForm = true;
    }

    public function saveDriver(): void
    {
        $data = $this->validate([
            'driver_nome' => 'required|string|max:255',
            'driver_cnh' => 'nullable|string|max:20',
            'driver_telefone' => 'nullable|string|max:30',
            'driver_ativo' => 'boolean',
        ]);

        $payload = [
            'nome' => $data['driver_nome'],
            'cnh' => $data['driver_cnh'] ?: null,
            'telefone' => $data['driver_telefone'] ?: null,
            'ativo' => $data['driver_ativo'],
        ];

        if ($this->editingDriverId) {
            $driver = DeliveryDriver::findOrFail($this->editingDriverId);
            $this->authorize('update', $driver);
            $driver->update($payload);
        } else {
            $this->authorize('create', DeliveryDriver::class);
            DeliveryDriver::create($payload);
        }

        $this->resetDriverForm();
        session()->flash('success', 'Motorista salvo.');
    }

    public function createVehicle(): void
    {
        $this->authorize('create', DeliveryVehicle::class);
        $this->resetVehicleForm();
        $this->showVehicleForm = true;
    }

    public function editVehicle(int $id): void
    {
        $vehicle = DeliveryVehicle::findOrFail($id);
        $this->authorize('update', $vehicle);
        $this->editingVehicleId = $vehicle->id;
        $this->vehicle_placa = $vehicle->placa;
        $this->vehicle_descricao = $vehicle->descricao;
        $this->vehicle_observacoes = $vehicle->observacoes ?? '';
        $this->vehicle_ativo = $vehicle->ativo;
        $this->showVehicleForm = true;
    }

    public function saveVehicle(): void
    {
        $data = $this->validate([
            'vehicle_placa' => 'required|string|max:15',
            'vehicle_descricao' => 'required|string|max:255',
            'vehicle_observacoes' => 'nullable|string|max:500',
            'vehicle_ativo' => 'boolean',
        ]);

        $payload = [
            'placa' => strtoupper($data['vehicle_placa']),
            'descricao' => $data['vehicle_descricao'],
            'observacoes' => $data['vehicle_observacoes'] ?: null,
            'ativo' => $data['vehicle_ativo'],
        ];

        if ($this->editingVehicleId) {
            $vehicle = DeliveryVehicle::findOrFail($this->editingVehicleId);
            $this->authorize('update', $vehicle);
            $vehicle->update($payload);
        } else {
            $this->authorize('create', DeliveryVehicle::class);
            DeliveryVehicle::create($payload);
        }

        $this->resetVehicleForm();
        session()->flash('success', 'Veículo salvo.');
    }

    public function render(): View
    {
        return view('livewire.logistics.logistics-fleet-index', [
            'drivers' => DeliveryDriver::query()->orderBy('nome')->get(),
            'vehicles' => DeliveryVehicle::query()->orderBy('placa')->get(),
            'canManageDrivers' => auth()->user()->can('create', DeliveryDriver::class),
            'canManageVehicles' => auth()->user()->can('create', DeliveryVehicle::class),
        ]);
    }

    private function resetDriverForm(): void
    {
        $this->showDriverForm = false;
        $this->editingDriverId = null;
        $this->driver_nome = '';
        $this->driver_cnh = '';
        $this->driver_telefone = '';
        $this->driver_ativo = true;
        $this->resetValidation();
    }

    private function resetVehicleForm(): void
    {
        $this->showVehicleForm = false;
        $this->editingVehicleId = null;
        $this->vehicle_placa = '';
        $this->vehicle_descricao = '';
        $this->vehicle_observacoes = '';
        $this->vehicle_ativo = true;
        $this->resetValidation();
    }
}
