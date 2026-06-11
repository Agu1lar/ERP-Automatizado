@php
    $canEditFicha = auth()->user()->can('updateFicha', $rental);
    $items = $rental->items;
@endphp

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Equipamentos a devolver</h3>
        <p class="text-sm text-gray-500 mt-0.5">Itens em campo com valores de locação e indenização — equivalente à aba “A devolver” do Sisloc.</p>
    </div>

    @if($items->isEmpty())
        <p class="px-6 py-8 text-sm text-gray-500">Nenhum item registrado. Os itens são criados automaticamente na saída da locação.</p>
    @else
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Patrimônio</th>
                    <th class="px-4 py-3 text-left">Descrição</th>
                    <th class="px-4 py-3 text-left">Local entrega</th>
                    <th class="px-4 py-3 text-right">Vl. locação</th>
                    <th class="px-4 py-3 text-right">Vl. indenização</th>
                    <th class="px-4 py-3 text-center">Qtd</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    @if($canEditFicha)
                        <th class="px-4 py-3 text-left">Indenização</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($items as $item)
                    <tr @class(['bg-gray-50/50' => ! $item->ativo])>
                        <td class="px-4 py-3">
                            @if($item->asset)
                                <a href="{{ route('assets.show', $item->asset) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $item->asset->codigo_patrimonio }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $item->descricao }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $item->local_entrega ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">R$ {{ number_format($item->valor_locacao, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($item->valor_indenizacao !== null)
                                R$ {{ number_format($item->valor_indenizacao, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">{{ $item->quantidade }}</td>
                        <td class="px-4 py-3">
                            @if($item->devolvido)
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Devolvido {{ $item->devolvido_em?->format('d/m/Y') }}</span>
                            @elseif($item->ativo)
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Em campo</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Substituído</span>
                            @endif
                        </td>
                        @if($canEditFicha)
                            <td class="px-4 py-3">
                                @if($item->ativo && ! $item->devolvido)
                                    <form wire:submit="saveItemIndemnity({{ $item->id }})" class="flex items-center gap-2">
                                        <input wire:model="item_indenizacao.{{ $item->id }}" type="number" step="0.01" min="0" class="w-28 rounded-md border-gray-300 text-xs" placeholder="0,00" />
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">Salvar</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 text-sm font-medium">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right text-gray-600">Totais (ativos em campo)</td>
                    <td class="px-4 py-3 text-right">
                        R$ {{ number_format($items->where('ativo', true)->where('devolvido', false)->sum(fn ($i) => $i->totalLocacao()), 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        R$ {{ number_format($items->where('ativo', true)->where('devolvido', false)->sum(fn ($i) => $i->totalIndenizacao()), 2, ',', '.') }}
                    </td>
                    <td colspan="{{ $canEditFicha ? 3 : 2 }}"></td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
