<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Contas a pagar</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Fornecedores de peças, oficina externa e lançamentos manuais</p>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm">
                        <span class="text-amber-800">Em aberto:</span>
                        <span class="font-semibold text-amber-900">R$ {{ number_format($openBalance, 2, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('finance.receivables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">A receber</a>
                    <a href="{{ route('finance.cashflow') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Fluxo de caixa</a>
                    @can('create', App\Models\Domain\Finance\PayableTitle::class)
                        <button wire:click="openCreateModal" class="btn-primary text-sm inline-flex items-center px-3 py-2 rounded-md">Novo título</button>
                    @endcan
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código, fornecedor, OS ou pedido..." class="rounded-md border-gray-300 shadow-sm max-w-md text-sm" />
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="originFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todas as origens</option>
                    @foreach($originOptions as $origin)
                        <option value="{{ $origin->value }}">{{ $origin->label() }}</option>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referência</th>
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
                                    <span class="text-gray-900">{{ $title->company->nome }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $title->originEnum()->label() }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    @if($title->partPurchaseOrder)
                                        <a href="{{ route('maintenance.purchase-orders.index') }}" wire:navigate class="text-indigo-600 hover:underline">{{ $title->partPurchaseOrder->codigo }}</a>
                                    @elseif($title->maintenanceOrder)
                                        <a href="{{ route('maintenance.show', $title->maintenanceOrder) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $title->maintenanceOrder->codigo }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
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
                                        @if($title->status === 'aberto')
                                            @can('markPaid', $title)
                                                <button wire:click="openPayModal({{ $title->id }})" class="text-emerald-600 hover:underline text-xs font-medium">Registrar pagamento</button>
                                            @endcan
                                            @can('update', $title)
                                                <button wire:click="cancelTitle({{ $title->id }})" wire:confirm="Cancelar este título?" class="text-gray-500 hover:underline text-xs">Cancelar</button>
                                            @endcan
                                        @elseif($title->status === 'pago' && $title->pago_em)
                                            <span class="text-xs text-gray-500">Pago {{ $title->pago_em->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Nenhuma conta a pagar encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $titles->links() }}
        </div>
    </div>

    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-4">Nova conta a pagar</h3>
                <form wire:submit="saveCreate" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Origem</label>
                        <select wire:model.live="create_origem" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach($originOptions as $origin)
                                <option value="{{ $origin->value }}">{{ $origin->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fornecedor / oficina *</label>
                        <select wire:model="create_company_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Selecione...</option>
                            @foreach($supplierOptions as $company)
                                <option value="{{ $company->id }}">{{ $company->nome }}</option>
                            @endforeach
                        </select>
                        @error('create_company_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor *</label>
                        <input wire:model="create_valor" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('create_valor') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vencimento *</label>
                        <input wire:model="create_vencimento" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Observações</label>
                        <textarea wire:model="create_observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="cancelCreate">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Salvar</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showPayModal && $payingTitle)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-2">Registrar pagamento</h3>
                <p class="text-sm text-gray-600 mb-4">{{ $payingTitle->codigo }} — {{ $payingTitle->company->nome }} — R$ {{ number_format($payingTitle->valor, 2, ',', '.') }}</p>
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
