@php
    use App\Enums\DeliveryManifestStopStatus;
    use App\Enums\LogisticsShift;
    $shiftLabel = fn (?string $v) => $v ? (LogisticsShift::tryFrom($v)?->label() ?? $v) : '—';
@endphp

<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <a href="{{ route('logistics.daily', ['data' => $manifest->data->toDateString()]) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Lista do dia</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">Romaneio {{ $manifest->codigo }}</h2>
                    <p class="text-sm text-gray-500">{{ $manifest->data->translatedFormat('d/m/Y') }} — {{ $manifest->statusEnum()->label() }}</p>
                </div>
                @if($canOperate && $manifest->statusEnum()->value === 'rascunho')
                    <x-btn-primary type="button" wire:click="startRoute">Iniciar rota</x-btn-primary>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow p-6 grid md:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Motorista</p>
                    <p class="font-medium">{{ $manifest->driver?->nome ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Veículo</p>
                    <p class="font-medium">{{ $manifest->vehicle?->displayLabel() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Progresso</p>
                    <p class="font-medium">{{ $manifest->completedStopsCount() }} / {{ $manifest->stops->count() }} paradas</p>
                </div>
            </div>

            @if($canOperate && in_array($manifest->statusEnum()->value, ['rascunho', 'em_rota'], true))
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="saveResources" class="grid md:grid-cols-3 gap-4 items-end text-sm">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Motorista</label>
                            <select wire:model="delivery_driver_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione...</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Veículo</label>
                            <select wire:model="delivery_vehicle_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione...</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-btn-secondary type="submit">Salvar equipe</x-btn-secondary>
                    </form>
                    <p class="text-xs text-gray-400 mt-2">
                        <a href="{{ route('logistics.fleet.index') }}" wire:navigate class="text-indigo-600 hover:underline">Cadastrar motoristas e veículos</a>
                    </p>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Locação</th>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Cliente / obra</th>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Turno</th>
                            <th class="px-4 py-3 text-left text-xs text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs text-gray-500 uppercase">Comprovante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($manifest->stops as $stop)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $stop->sequencia }}</td>
                                <td class="px-4 py-3">{{ $stop->tipoEnum()->label() }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('rentals.show', $stop->rental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $stop->rental->codigo }}</a>
                                    <div class="text-xs text-gray-500">{{ $stop->rental->asset->codigo_patrimonio }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ $stop->rental->customer->nome }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs" title="{{ $stop->endereco }}">{{ $stop->endereco ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $shiftLabel($stop->turno) }}</td>
                                <td class="px-4 py-3">{{ $stop->statusEnum()->label() }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($stop->proof)
                                        <span class="text-emerald-700 text-xs">✓ {{ $stop->proof->receptor_nome }}</span>
                                    @elseif($canOperate && $manifest->statusEnum()->value === 'em_rota')
                                        <a href="{{ route('logistics.manifest.stop.proof', [$manifest, $stop]) }}" wire:navigate class="text-indigo-600 hover:underline text-xs font-medium">Registrar</a>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
