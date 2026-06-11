<x-flash-message />

<div @billing-download.window="window.open($event.detail.url, '_blank')">
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('livewire.finance.partials.billing-result-banner')
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Faturas a emitir</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Fila de locações, renovações e indenizações pendentes — equivalente ao relatório de NF do Sisloc.</p>
                </div>
                @if($pendingCount > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                        {{ $pendingCount }} pendente(s)
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('statusFilter', 'pendente')" @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium border',
                    'bg-indigo-50 border-indigo-200 text-indigo-700' => $statusFilter === 'pendente',
                    'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' => $statusFilter !== 'pendente',
                ])>
                    A faturar
                    @if($pendingCount > 0)
                        <span class="ml-1 text-xs opacity-80">({{ $pendingCount }})</span>
                    @endif
                </button>
                @foreach($statusOptions as $option)
                    @php $count = $statusCounts[$option->value] ?? 0; @endphp
                    <button wire:click="$set('statusFilter', '{{ $option->value }}')" @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium border',
                        'bg-indigo-50 border-indigo-200 text-indigo-700' => $statusFilter === $option->value,
                        'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' => $statusFilter !== $option->value,
                    ])>
                        {{ $option->label() }}
                        @if($count > 0)
                            <span class="ml-1 text-xs opacity-80">({{ $count }})</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @foreach($report['grupos'] as $grupo)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap justify-between gap-2">
                        <h3 class="font-semibold text-gray-800">{{ $grupo['label'] }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ $grupo['registros'] }} registro(s) —
                            NF: R$ {{ number_format($grupo['total_nf'], 2, ',', '.') }} |
                            CAR: R$ {{ number_format($grupo['total_car'], 2, ',', '.') }}
                        </p>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Cliente</th>
                                <th class="px-4 py-3 text-left">Origem</th>
                                <th class="px-4 py-3 text-left">Código</th>
                                <th class="px-4 py-3 text-left">Geração</th>
                                <th class="px-4 py-3 text-left">Período</th>
                                <th class="px-4 py-3 text-right">Valor NF</th>
                                <th class="px-4 py-3 text-right">Valor CAR</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($grupo['entries'] as $entry)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('customers.show', $entry->customer) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $entry->customer->nome }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('rentals.show', $entry->rental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $entry->origemLabel() }}</a>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $entry->codigo }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $entry->gerado_em->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-xs">
                                        @if($entry->periodo_inicio && $entry->periodo_fim)
                                            {{ $entry->periodo_inicio->format('d/m/Y') }} — {{ $entry->periodo_fim->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">R$ {{ number_format($entry->valor_nf, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($entry->valor_car, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :status="$entry->statusEnum()" />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            @if($entry->statusEnum() === \App\Enums\RentalBillingQueueStatus::Pendente)
                                                @can('create', App\Models\Domain\Finance\ReceivableTitle::class)
                                                    <div class="space-x-2">
                                                        <button wire:click="authorizeEntry({{ $entry->id }})" class="text-xs text-indigo-600 hover:underline">Autorizar</button>
                                                        <button wire:click="invoiceEntry({{ $entry->id }})" class="text-xs text-emerald-600 hover:underline font-medium">Gerar fatura</button>
                                                    </div>
                                                @endcan
                                            @elseif($entry->statusEnum() === \App\Enums\RentalBillingQueueStatus::Autorizado)
                                                @can('create', App\Models\Domain\Finance\ReceivableTitle::class)
                                                    <button wire:click="invoiceEntry({{ $entry->id }})" class="text-xs text-emerald-600 hover:underline font-medium">Gerar fatura</button>
                                                @endcan
                                            @endif
                                            <x-billing-entry-actions :entry="$entry" :compact="true" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            @if($report['total_registros'] === 0)
                <div class="bg-white rounded-lg shadow p-8 text-center space-y-4">
                    <p class="text-gray-500">Nenhum registro neste filtro.</p>
                    <div class="flex flex-wrap justify-center gap-2 text-sm">
                        @if($statusFilter !== 'pendente' && $pendingCount > 0)
                            <button type="button" wire:click="$set('statusFilter', 'pendente')" class="text-indigo-600 hover:underline font-medium">
                                Voltar para a faturar ({{ $pendingCount }})
                            </button>
                        @endif
                        @if($statusFilter !== 'autorizado' && ($statusCounts['autorizado'] ?? 0) > 0)
                            <button type="button" wire:click="$set('statusFilter', 'autorizado')" class="text-indigo-600 hover:underline font-medium">
                                Ver autorizados ({{ $statusCounts['autorizado'] }})
                            </button>
                        @endif
                        @if($statusFilter !== 'faturado' && ($statusCounts['faturado'] ?? 0) > 0)
                            <button type="button" wire:click="$set('statusFilter', 'faturado')" class="text-indigo-600 hover:underline font-medium">
                                Ver faturados ({{ $statusCounts['faturado'] }})
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-gray-50 rounded-lg border border-gray-200 px-6 py-4 flex flex-wrap justify-between gap-4 text-sm">
                    <span><strong>{{ $report['total_registros'] }}</strong> registro(s)</span>
                    <span>Total NF: <strong>R$ {{ number_format($report['total_nf'], 2, ',', '.') }}</strong></span>
                    <span>Total CAR: <strong>R$ {{ number_format($report['total_car'], 2, ',', '.') }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    @include('livewire.finance.partials.billing-pay-modal')
</div>
