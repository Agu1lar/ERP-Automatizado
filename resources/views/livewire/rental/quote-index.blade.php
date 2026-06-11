<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Orçamentos</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Pré-reserva com validade — converta em locação reservada</p>
                </div>
                @can('create', App\Models\Domain\Rental\RentalQuote::class)
                    <x-btn-primary wire:click="openForm" type="button">Novo orçamento</x-btn-primary>
                @endcan
            </div>

            <div class="flex flex-wrap gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código, cliente ou patrimônio..." class="rounded-md border-gray-300 shadow-sm max-w-md text-sm" />
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos status</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validade</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor est.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($quotes as $quote)
                            <tr @class(['bg-amber-50' => $quote->statusEnum()->value === 'enviado' && $quote->isExpired()])>
                                <td class="px-4 py-3 font-medium">{{ $quote->codigo }}</td>
                                <td class="px-4 py-3">{{ $quote->customer->nome }}</td>
                                <td class="px-4 py-3">{{ $quote->asset->codigo_patrimonio }}</td>
                                <td class="px-4 py-3">
                                    @if($quote->valid_until)
                                        {{ $quote->valid_until->format('d/m/Y H:i') }}
                                        @if($quote->statusEnum()->value === 'enviado' && ! $quote->isExpired())
                                            <span class="text-xs text-gray-500 block">{{ $quote->daysUntilExpiry() }} dia(s)</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($quote->valor_estimado)
                                        R$ {{ number_format($quote->valor_estimado, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $quote->statusEnum()->label() }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @if($quote->rental)
                                        <a href="{{ route('rentals.show', $quote->rental) }}" wire:navigate class="text-indigo-600 hover:underline text-xs">{{ $quote->rental->codigo }}</a>
                                    @endif
                                    @can('convert', $quote)
                                        @if(! $quote->isExpired())
                                            <button wire:click="convert({{ $quote->id }})" wire:confirm="Converter em reserva?" class="text-emerald-600 hover:underline text-xs font-medium">Converter</button>
                                        @endif
                                    @endcan
                                    @can('cancel', $quote)
                                        <button wire:click="cancel({{ $quote->id }})" wire:confirm="Cancelar orçamento?" class="text-red-600 hover:underline text-xs">Cancelar</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum orçamento encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $quotes->links() }}</div>
            </div>
        </div>
    </div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Novo orçamento</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Patrimônio</label>
                        <input wire:model.live.debounce.300ms="asset_search" type="search" placeholder="Buscar PAT-..." class="w-full rounded-md border-gray-300 text-sm" />
                        @if($asset_id)
                            <p class="text-xs text-emerald-600 mt-1">Selecionado #{{ $asset_id }}</p>
                        @endif
                        @if($assetSuggestions->isNotEmpty())
                            <ul class="mt-1 border rounded-md divide-y text-sm max-h-32 overflow-y-auto">
                                @foreach($assetSuggestions as $asset)
                                    <li>
                                        <button type="button" wire:click="$set('asset_id', {{ $asset->id }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50">
                                            {{ $asset->codigo_patrimonio }} — {{ $asset->equipmentDisplayName() }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('asset_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                        <input wire:model.live.debounce.300ms="customer_search" type="search" placeholder="Nome do cliente..." class="w-full rounded-md border-gray-300 text-sm" />
                        @if($customer_id)
                            <p class="text-xs text-emerald-600 mt-1">Selecionado #{{ $customer_id }}</p>
                        @endif
                        @if($customerSuggestions->isNotEmpty())
                            <ul class="mt-1 border rounded-md divide-y text-sm max-h-32 overflow-y-auto">
                                @foreach($customerSuggestions as $customer)
                                    <li>
                                        <button type="button" wire:click="$set('customer_id', {{ $customer->id }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50">
                                            {{ $customer->nome }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('customer_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Retorno previsto</label>
                            <input wire:model="expected_return_at" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Validade (dias)</label>
                            <input wire:model="validity_days" type="number" min="1" max="90" class="w-full rounded-md border-gray-300 text-sm" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Período de preço</label>
                        <select wire:model="pricing_period" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Automático</option>
                            @foreach($periodOptions as $period)
                                <option value="{{ $period->value }}">{{ $period->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Local da obra</label>
                        <input wire:model="local_obra" type="text" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea wire:model="observacoes" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <x-btn-primary type="submit">Enviar orçamento</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
