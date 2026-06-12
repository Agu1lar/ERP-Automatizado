<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Fluxo de caixa previsto</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Entradas (a receber) e saídas (a pagar) por vencimento</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('finance.receivables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">A receber</a>
                    <a href="{{ route('finance.payables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">A pagar</a>
                </div>
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
                    <p class="text-xs text-emerald-700">Entradas previstas</p>
                    <p class="text-lg font-bold text-emerald-900">R$ {{ number_format($totalInflows, 2, ',', '.') }}</p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
                    <p class="text-xs text-amber-700">Saídas previstas</p>
                    <p class="text-lg font-bold text-amber-900">R$ {{ number_format($totalOutflows, 2, ',', '.') }}</p>
                </div>
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2">
                    <p class="text-xs text-indigo-700">Saldo previsto</p>
                    <p class="text-lg font-bold text-indigo-900">R$ {{ number_format($totalInflows - $totalOutflows, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 bg-emerald-50 border-b border-emerald-100">
                        <h3 class="text-sm font-semibold text-emerald-900">Entradas — títulos a receber</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($inflowRows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($row->vencimento)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ $row->quantidade }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-700">R$ {{ number_format($row->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">Nenhuma entrada no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
                        <h3 class="text-sm font-semibold text-amber-900">Saídas — contas a pagar</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtd</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($outflowRows as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($row->vencimento)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ $row->quantidade }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-amber-700">R$ {{ number_format($row->valor, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">Nenhuma saída no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
