<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                    Categorias de Equipamento
                    <x-help-hint text="Agrupe equipamentos por tipo (ex.: Marteletes, Betoneiras). Cada patrimônio pertence a um modelo, e cada modelo a uma categoria." />
                </h2>
                @can('create', App\Models\Domain\Fleet\EquipmentCategory::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">
                            + Nova categoria
                            <x-help-hint text="Crie um grupo para organizar modelos e patrimônios. O tipo de linha costuma ser 'linha_leve' neste sistema." class="ml-2" />
                        </x-btn-primary>
                    @endunless
                @endcan
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar categoria..." class="w-full max-w-md rounded-md border-gray-300 shadow-sm" />
                <x-archive-filter />
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo de linha</label>
                            <input wire:model="tipo_linha" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm text-gray-700">Ativo</span>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modelos</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônios</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($categories as $category)
                            @php $counts = $assetCounts[$category->id] ?? ['total' => 0, 'disponivel' => 0, 'locado' => 0, 'manutencao' => 0]; @endphp
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-4 py-3 text-sm font-medium">
                                    <a href="{{ route('fleet.categories.show', $category) }}" wire:navigate data-tab-title="{{ $category->nome }}" class="text-indigo-600 hover:underline">
                                        {{ $category->nome }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $category->tipo_linha }}</td>
                                <td class="px-4 py-3 text-sm">{{ $category->models_count }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if($counts['total'] > 0)
                                        <div class="flex flex-wrap gap-1.5 text-xs">
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800" title="Disponíveis">{{ $counts['disponivel'] }} disp.</span>
                                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-800" title="Locados">{{ $counts['locado'] }} loc.</span>
                                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-orange-800" title="Em manutenção">{{ $counts['manutencao'] }} manut.</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="{{ $category->ativo ? 'text-green-600' : 'text-gray-400' }}">{{ $category->ativo ? 'Ativo' : 'Inativo' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-3">
                                    @unless($showArchived)
                                        <a href="{{ route('fleet.categories.show', $category) }}" wire:navigate data-tab-title="{{ $category->nome }}" class="text-indigo-600 text-sm hover:underline">Ver patrimônios</a>
                                        @can('update', $category)
                                            <button wire:click="edit({{ $category->id }})" class="text-gray-600 text-sm hover:underline">Editar</button>
                                        @endcan
                                    @endunless
                                    <x-archive-record-button :model="$category" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $categories->links() }}</div>
            </div>
        </div>
    </div>
</div>
