<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Fluxo de caixa previsto</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Entradas por vencimento de títulos em aberto</p>
                </div>
                <a href="{{ route('finance.receivables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Títulos</a>
            </div>

            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700">De</label>
                    <input wire:model.live="date_from" type="date" class="mt-1 rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Até</label>
                    <input wire:model.live="date_to" type="date" class="mt-1 rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                    <p class="text-xs text-emerald-700">Total previsto no período</p>
                    <p class="text-lg font-bold text-emerald-900">R$ {{ number_format($totalExpected, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Títulos</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor previsto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rows as $row)
                            <tr>
                                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($row->vencimento)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->quantidade }}</td>
                                <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($row->valor, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">Nenhum vencimento no período selecionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
