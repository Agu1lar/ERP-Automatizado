<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Títulos a receber</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Parcelas por locação — baixa manual de pagamento</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('finance.delinquency') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Inadimplência</a>
                    <a href="{{ route('finance.cashflow') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Fluxo de caixa</a>
                    <a href="{{ route('finance.export') }}" class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Exportar CSV</a>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Exportar contábil ▾
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-48 rounded-md border border-gray-200 bg-white shadow-lg z-10 py-1">
                            <a href="{{ route('finance.accounting.export', ['format' => 'csv', 'status' => 'aberto']) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">CSV padrão</a>
                            <a href="{{ route('finance.accounting.export', ['format' => 'omie', 'status' => 'aberto']) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Omie</a>
                            <a href="{{ route('finance.accounting.export', ['format' => 'bling', 'status' => 'aberto']) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Bling</a>
                            <a href="{{ route('finance.accounting.export', ['format' => 'sisloc', 'status' => 'aberto']) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50">Sisloc (legado)</a>
                            <p class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100 mt-1">Somente títulos abertos — evita duplicar no ERP fiscal.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código, cliente ou locação..." class="rounded-md border-gray-300 shadow-sm max-w-md text-sm" />
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input wire:model.live="overdueOnly" type="checkbox" value="1" class="rounded border-gray-300" />
                    Somente atrasados
                </label>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locação</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Parcela</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($titles as $title)
                            <tr @class(['bg-red-50' => $title->isOverdue()])>
                                <td class="px-4 py-3 font-medium">{{ $title->codigo }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('customers.show', $title->customer) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $title->customer->nome }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if($title->rental)
                                        <a href="{{ route('rentals.show', $title->rental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $title->rental->codigo }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $title->parcelLabel() }}</td>
                                <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($title->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    {{ $title->vencimento->format('d/m/Y') }}
                                    @if($title->isOverdue())
                                        <span class="text-red-600 text-xs block">{{ $title->daysOverdue() }} dias atraso</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $title->statusEnum()->label() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-col items-end gap-1">
                                        <a href="{{ route('finance.receivable.export', $title) }}" target="_blank" class="text-indigo-600 hover:underline text-xs">Exportar CSV</a>
                                        @if($title->status === 'aberto')
                                            @can('markPaid', $title)
                                                <button wire:click="openPayModal({{ $title->id }})" class="text-emerald-600 hover:underline text-xs font-medium">Registrar pagamento</button>
                                            @endcan
                                        @elseif($title->status === 'pago' && $title->pago_em)
                                            <span class="text-xs text-gray-500">Pago {{ $title->pago_em->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Nenhum título encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $titles->links() }}
        </div>
    </div>

    @if($showPayModal && $payingTitle)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-2">Registrar pagamento</h3>
                <p class="text-sm text-gray-600 mb-4">{{ $payingTitle->codigo }} — R$ {{ number_format($payingTitle->valor, 2, ',', '.') }}</p>
                <form wire:submit="confirmPayment" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data do pagamento</label>
                        <input wire:model="pay_pago_em" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                        <select wire:model="pay_method" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Observações</label>
                        <textarea wire:model="pay_observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    @error('pay') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="cancelPayment">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Confirmar baixa</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
