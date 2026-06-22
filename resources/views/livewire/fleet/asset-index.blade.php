<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <h2 class="text-xl font-semibold text-gray-800">Patrimônios</h2>
                @can('create', App\Models\Domain\Fleet\Asset::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">+ Novo patrimônio</x-btn-primary>
                    @endunless
                @endcan
            </div>

            <div class="flex flex-wrap gap-4 items-center">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Código, série, marca..." class="rounded-md border-gray-300 shadow-sm" />
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm">
                    <option value="">Todos status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="categoryFilter" class="rounded-md border-gray-300 shadow-sm">
                    <option value="">Todas categorias</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                    @endforeach
                </select>
                <x-archive-filter />
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-2xl">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Código patrimônio *</label>
                                <input wire:model="codigo_patrimonio" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                @error('codigo_patrimonio') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Modelo *</label>
                                <select wire:model="equipment_model_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione...</option>
                                    @foreach($models as $model)
                                        <option value="{{ $model->id }}">{{ $model->category->nome }} — {{ $model->displayName() }}</option>
                                    @endforeach
                                </select>
                                @error('equipment_model_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nº série</label>
                                <input wire:model="serie" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                            @can('fleet.assets.manage')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Valor de aquisição (R$)</label>
                                    <input wire:model="valor_compra" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                            @endcan
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Data de aquisição</label>
                                <input wire:model="data_compra" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Localização</label>
                                <input wire:model="localizacao" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                        </div>
                        @if(!$editingId)
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status inicial</label>
                                    <select wire:model.live="initial_status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="disponivel">Disponível</option>
                                        <option value="bloqueado">Bloqueado</option>
                                    </select>
                                </div>
                                @if($initial_status === 'bloqueado')
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Motivo bloqueio *</label>
                                        <input wire:model="motivo_bloqueio" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                        @error('motivo_bloqueio') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Equipamento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localização</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($assets as $asset)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium">{{ $asset->codigo_patrimonio }}</td>
                                <td class="px-4 py-3 text-sm">{{ $asset->equipmentDisplayName() }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-status-badge :status="$asset->statusEnum()" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $asset->localizacao ?? '—' }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @unless($showArchived)
                                        <a href="{{ route('assets.show', $asset) }}" wire:navigate class="text-indigo-600 text-sm hover:underline">Ficha</a>
                                        @can('update', $asset)
                                            <button wire:click="edit({{ $asset->id }})" class="text-gray-600 text-sm hover:underline">Editar</button>
                                        @endcan
                                    @endunless
                                    <x-archive-record-button :model="$asset" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $assets->links() }}</div>
            </div>
        </div>
    </div>
</div>
