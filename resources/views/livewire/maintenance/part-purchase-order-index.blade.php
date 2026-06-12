<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="{{ route('maintenance.parts.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Catálogo de peças</a>
                    <h2 class="text-xl font-semibold text-gray-800 mt-1">Pedidos de compra</h2>
                    <p class="text-sm text-gray-500">Fornecedores de peças, pedidos e entrada automática no estoque ao receber.</p>
                </div>
                @if($canManage)
                    <x-btn-primary wire:click="create">+ Novo pedido</x-btn-primary>
                @endif
            </div>

            @if($lowStockItems->isNotEmpty())
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="font-medium text-amber-900">{{ $lowStockItems->count() }} peça(s) abaixo do estoque mínimo</p>
                    <ul class="mt-2 text-amber-800 space-y-1">
                        @foreach($lowStockItems->take(5) as $item)
                            <li>{{ $item->codigo_peca }} — {{ $item->descricao }} ({{ number_format($item->estoque_atual, 2, ',', '.') }} / mín. {{ number_format($item->estoque_minimo, 2, ',', '.') }})</li>
                        @endforeach
                    </ul>
                    @if($canManage)
                        <div class="mt-3 flex flex-wrap gap-2 items-end">
                            <div>
                                <label class="block text-xs text-amber-800">Fornecedor</label>
                                <select wire:model="supplier_id" class="mt-1 rounded-md border-amber-200 text-sm">
                                    <option value="">Selecione...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="createFromLowStock" class="text-sm text-indigo-700 hover:underline">Gerar pedido do estoque baixo</button>
                            <a href="{{ route('person.companies.index') }}" wire:navigate class="text-xs text-gray-500 hover:underline">Cadastrar fornecedor no CRM</a>
                        </div>
                    @endif
                </div>
            @endif

            @if($showForm && $canManage)
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">Novo pedido de compra</h3>
                    <form wire:submit="save" class="space-y-4 text-sm">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-gray-700">Fornecedor *</label>
                                <select wire:model="supplier_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->nome }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block font-medium text-gray-700">Observações</label>
                                <input wire:model="observacoes" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-700">Itens</span>
                                <button type="button" wire:click="addItemRow" class="text-indigo-600 hover:underline text-xs">+ Linha</button>
                            </div>
                            @foreach($items as $index => $row)
                                <div class="grid md:grid-cols-4 gap-2 items-end">
                                    <select wire:model="items.{{ $index }}.part_catalog_item_id" class="rounded-md border-gray-300 shadow-sm md:col-span-2">
                                        <option value="0">Peça...</option>
                                        @foreach($catalogItems as $catalogItem)
                                            <option value="{{ $catalogItem->id }}">{{ $catalogItem->codigo_peca }} — {{ $catalogItem->descricao }}</option>
                                        @endforeach
                                    </select>
                                    <input wire:model="items.{{ $index }}.quantidade" type="number" step="0.01" min="0.01" placeholder="Qtd" class="rounded-md border-gray-300 shadow-sm" />
                                    <div class="flex gap-2">
                                        <input wire:model="items.{{ $index }}.valor_unitario" type="number" step="0.01" min="0" placeholder="R$ un." class="w-full rounded-md border-gray-300 shadow-sm" />
                                        @if(count($items) > 1)
                                            <button type="button" wire:click="removeItemRow({{ $index }})" class="text-red-600 text-xs">×</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar pedido</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="$set('showForm', false)">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif

            <div class="flex gap-3 items-center">
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pedido</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $order->codigo }}</td>
                                <td class="px-4 py-3">{{ $order->supplier->nome }}</td>
                                <td class="px-4 py-3">
                                    {{ $order->statusEnum()->label() }}
                                    @if($order->payableTitle)
                                        <a href="{{ route('finance.payables') }}" wire:navigate class="block text-xs text-indigo-600 hover:underline">{{ $order->payableTitle->codigo }}</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->items->count() }} peça(s)</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @if($canManage && $order->statusEnum() === \App\Enums\PartPurchaseOrderStatus::Rascunho)
                                        <button wire:click="send({{ $order->id }})" class="text-indigo-600 hover:underline text-xs">Enviar</button>
                                        <button wire:click="cancel({{ $order->id }})" class="text-red-600 hover:underline text-xs">Cancelar</button>
                                    @endif
                                    @if($canManage && in_array($order->statusEnum(), [\App\Enums\PartPurchaseOrderStatus::Enviado, \App\Enums\PartPurchaseOrderStatus::RecebidoParcial], true))
                                        <button wire:click="receive({{ $order->id }})" class="text-emerald-700 hover:underline text-xs font-medium">Receber</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhum pedido de compra.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        </div>
    </div>
</div>
