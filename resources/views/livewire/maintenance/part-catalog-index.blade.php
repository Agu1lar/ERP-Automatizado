<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="{{ route('maintenance.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar para OS</a>
                    <h2 class="text-xl font-semibold text-gray-800 mt-1">Catálogo de peças</h2>
                    <p class="text-sm text-gray-500">Peças cadastradas aparecem no autocomplete na OS. Ao concluir a OS, o estoque é baixado automaticamente.</p>
                </div>
                @if($canManage)
                    <div class="flex gap-2">
                        <a href="{{ route('maintenance.purchase-orders.index') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Pedidos de compra</a>
                        <x-btn-primary wire:click="create">+ Nova peça</x-btn-primary>
                    </div>
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
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <input wire:model="estoque_atual" type="number" step="0.01" min="0" placeholder="Estoque atual *" class="w-full rounded-md border-gray-300 shadow-sm" />
                                @error('estoque_atual') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <input wire:model="estoque_minimo" type="number" step="0.01" min="0" placeholder="Estoque mínimo" class="w-full rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span>Ativo no catálogo</span>
                        </label>
                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                    @if($editingId && $priceHistory->isNotEmpty())
                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Histórico de preço por fornecedor</h4>
                            <ul class="text-xs text-gray-600 space-y-1">
                                @foreach($priceHistory->take(8) as $price)
                                    <li>{{ $price->created_at->format('d/m/Y') }} — {{ $price->supplier->nome }}: R$ {{ number_format($price->valor_unitario, 2, ',', '.') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque</th>
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
                                <td class="px-4 py-3 {{ $item->isBelowMinimum() ? 'text-amber-700 font-medium' : '' }}">
                                    {{ number_format($item->estoque_atual, 2, ',', '.') }}
                                    @if($item->estoque_minimo !== null)
                                        <span class="text-xs text-gray-400">/ mín. {{ number_format($item->estoque_minimo, 2, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $item->ativo ? 'Ativo' : 'Inativo' }}</td>
                                @if($canManage)
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="edit({{ $item->id }})" class="text-indigo-600 hover:underline text-xs">Editar</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="px-4 py-8 text-center text-gray-500">Nenhuma peça cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links() }}
        </div>
    </div>
</div>

