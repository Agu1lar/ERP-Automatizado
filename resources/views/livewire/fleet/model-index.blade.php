<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Modelos de Equipamento</h2>
                @can('create', App\Models\Domain\Fleet\EquipmentModel::class)
                    <x-btn-primary wire:click="create">+ Novo modelo</x-btn-primary>
                @endcan
            </div>
            <div class="flex flex-wrap gap-4">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar marca ou modelo..." class="rounded-md border-gray-300 shadow-sm" />
                <select wire:model.live="categoryFilter" class="rounded-md border-gray-300 shadow-sm">
                    <option value="">Todas categorias</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                    @endforeach
                </select>
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-2xl">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select wire:model="equipment_category_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                                @endforeach
                            </select>
                            @error('equipment_category_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Marca</label>
                                <input wire:model="marca" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                @error('marca') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Modelo</label>
                                <input wire:model="modelo" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                @error('modelo') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Especificações (JSON opcional)</label>
                            <textarea wire:model="especificacoes" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm font-mono text-sm"></textarea>
                            @error('especificacoes') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm">Ativo</span>
                        </label>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca / Modelo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($models as $model)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium">{{ $model->displayName() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $model->category->nome }}</td>
                                <td class="px-4 py-3 text-sm">{{ $model->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $model)
                                        <button wire:click="edit({{ $model->id }})" class="text-indigo-600 text-sm hover:underline">Editar</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $models->links() }}</div>
            </div>
        </div>
    </div>
</div>
