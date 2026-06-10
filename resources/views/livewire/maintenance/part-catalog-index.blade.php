<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="{{ route('maintenance.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar para OS</a>
                    <h2 class="text-xl font-semibold text-gray-800 mt-1">Catálogo de peças</h2>
                    <p class="text-sm text-gray-500">Peças cadastradas aparecem no autocomplete ao adicionar itens na OS.</p>
                </div>
                @if($canManage)
                    <x-btn-primary wire:click="create">+ Nova peça</x-btn-primary>
                @endif
            </div>

            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código ou descrição..." class="rounded-md border-gray-300 shadow-sm max-w-md" />

            @if($showForm && $canManage)
                <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                    <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar peça' : 'Nova peça' }}</h3>
                    <form wire:submit="save" class="space-y-3 text-sm">
                        <input wire:model="codigo_peca" type="text" placeholder="Código da peça *" class="w-full rounded-md border-gray-300 shadow-sm" />
                        @error('codigo_peca') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        <input wire:model="codigo_alternativo" type="text" placeholder="Código alternativo" class="w-full rounded-md border-gray-300 shadow-sm" />
                        <input wire:model="descricao" type="text" placeholder="Descrição *" class="w-full rounded-md border-gray-300 shadow-sm" />
                        @error('descricao') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        <input wire:model="valor_unitario_padrao" type="number" step="0.01" min="0" placeholder="Valor padrão (R$)" class="w-full rounded-md border-gray-300 shadow-sm" />
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span>Ativo no catálogo</span>
                        </label>
                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alternativo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            @if($canManage)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">{{ $item->codigo_peca }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $item->codigo_alternativo ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $item->descricao }}</td>
                                <td class="px-4 py-3">{{ $item->valor_unitario_padrao !== null ? 'R$ '.number_format($item->valor_unitario_padrao, 2, ',', '.') : '—' }}</td>
                                <td class="px-4 py-3">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</td>
                                @if($canManage)
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="edit({{ $item->id }})" class="text-indigo-600 hover:underline text-xs">Editar</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-500">Nenhuma peça cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links() }}
        </div>
    </div>
</div>
