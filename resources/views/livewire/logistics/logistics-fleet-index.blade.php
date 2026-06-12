<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div>
                <a href="{{ route('logistics.daily') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Lista do dia</a>
                <h2 class="text-xl font-semibold text-gray-800 mt-1">Motoristas e veículos de entrega</h2>
                <p class="text-sm text-gray-500">Cadastro usado nos romaneios da frota própria.</p>
            </div>

            <div class="flex gap-2 border-b border-gray-200 pb-1">
                <button type="button" wire:click="$set('tab', 'motoristas')" @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', 'border-indigo-600 text-indigo-700' => $tab === 'motoristas', 'border-transparent text-gray-500' => $tab !== 'motoristas'])>Motoristas</button>
                <button type="button" wire:click="$set('tab', 'veiculos')" @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', 'border-indigo-600 text-indigo-700' => $tab === 'veiculos', 'border-transparent text-gray-500' => $tab !== 'veiculos'])>Veículos</button>
            </div>

            @if($tab === 'motoristas')
                @if($canManageDrivers)
                    <div class="flex justify-end">
                        <x-btn-primary wire:click="createDriver">+ Motorista</x-btn-primary>
                    </div>
                @endif

                @if($showDriverForm && $canManageDrivers)
                    <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                        <h3 class="font-semibold">{{ $editingDriverId ? 'Editar motorista' : 'Novo motorista' }}</h3>
                        <form wire:submit="saveDriver" class="grid md:grid-cols-2 gap-3">
                            <input wire:model="driver_nome" type="text" placeholder="Nome *" class="rounded-md border-gray-300 shadow-sm md:col-span-2" />
                            <input wire:model="driver_cnh" type="text" placeholder="CNH" class="rounded-md border-gray-300 shadow-sm" />
                            <input wire:model="driver_telefone" type="text" placeholder="Telefone" class="rounded-md border-gray-300 shadow-sm" />
                            <label class="flex items-center gap-2 md:col-span-2"><input wire:model="driver_ativo" type="checkbox" class="rounded border-gray-300" /> Ativo</label>
                            <div class="md:col-span-2 flex gap-2"><x-btn-primary type="submit">Salvar</x-btn-primary><x-btn-secondary type="button" wire:click="$set('showDriverForm', false)">Cancelar</x-btn-secondary></div>
                        </form>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Nome</th><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">CNH</th><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Telefone</th><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Status</th>@if($canManageDrivers)<th></th>@endif</tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($drivers as $driver)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $driver->nome }}</td>
                                    <td class="px-4 py-3">{{ $driver->cnh ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $driver->telefone ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $driver->ativo ? 'Ativo' : 'Inativo' }}</td>
                                    @if($canManageDrivers)<td class="px-4 py-3 text-right"><button wire:click="editDriver({{ $driver->id }})" class="text-indigo-600 text-xs hover:underline">Editar</button></td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageDrivers ? 5 : 4 }}" class="px-4 py-8 text-center text-gray-500">Nenhum motorista cadastrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'veiculos')
                @if($canManageVehicles)
                    <div class="flex justify-end">
                        <x-btn-primary wire:click="createVehicle">+ Veículo</x-btn-primary>
                    </div>
                @endif

                @if($showVehicleForm && $canManageVehicles)
                    <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                        <h3 class="font-semibold">{{ $editingVehicleId ? 'Editar veículo' : 'Novo veículo' }}</h3>
                        <form wire:submit="saveVehicle" class="grid md:grid-cols-2 gap-3">
                            <input wire:model="vehicle_placa" type="text" placeholder="Placa *" class="rounded-md border-gray-300 shadow-sm" />
                            <input wire:model="vehicle_descricao" type="text" placeholder="Descrição *" class="rounded-md border-gray-300 shadow-sm" />
                            <input wire:model="vehicle_observacoes" type="text" placeholder="Observações" class="rounded-md border-gray-300 shadow-sm md:col-span-2" />
                            <label class="flex items-center gap-2 md:col-span-2"><input wire:model="vehicle_ativo" type="checkbox" class="rounded border-gray-300" /> Ativo</label>
                            <div class="md:col-span-2 flex gap-2"><x-btn-primary type="submit">Salvar</x-btn-primary><x-btn-secondary type="button" wire:click="$set('showVehicleForm', false)">Cancelar</x-btn-secondary></div>
                        </form>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Placa</th><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Descrição</th><th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Status</th>@if($canManageVehicles)<th></th>@endif</tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($vehicles as $vehicle)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $vehicle->placa }}</td>
                                    <td class="px-4 py-3">{{ $vehicle->descricao }}</td>
                                    <td class="px-4 py-3">{{ $vehicle->ativo ? 'Ativo' : 'Inativo' }}</td>
                                    @if($canManageVehicles)<td class="px-4 py-3 text-right"><button wire:click="editVehicle({{ $vehicle->id }})" class="text-indigo-600 text-xs hover:underline">Editar</button></td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManageVehicles ? 4 : 3 }}" class="px-4 py-8 text-center text-gray-500">Nenhum veículo cadastrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
