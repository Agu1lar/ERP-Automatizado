@props([
    'rental',
    'status',
    'steps' => [],
    'canOpenMaintenanceOrder' => false,
    'canGenerateReceivables' => false,
])

<div id="workflow" class="bg-white rounded-lg shadow overflow-hidden">
    <div class="border-b border-gray-100 px-6 py-4">
        <h3 class="font-semibold text-gray-800">Fluxo da ficha</h3>
        <p class="text-sm text-gray-500 mt-0.5">Siga as etapas na ordem — ações disponíveis aparecem abaixo conforme o status atual.</p>
    </div>

    <div class="px-4 py-5 sm:px-6 overflow-x-auto">
        <ol class="flex min-w-[42rem] items-start gap-1">
            @foreach($steps as $index => $step)
                @php
                    $stateClasses = match ($step['state']) {
                        'completed' => 'border-emerald-500 bg-emerald-50 text-emerald-800',
                        'current' => 'border-indigo-600 bg-indigo-50 text-indigo-900 ring-2 ring-indigo-200',
                        'cancelled' => 'border-gray-300 bg-gray-100 text-gray-500 line-through',
                        default => 'border-gray-200 bg-white text-gray-400',
                    };
                    $dotClasses = match ($step['state']) {
                        'completed' => 'bg-emerald-500 text-white',
                        'current' => 'bg-indigo-600 text-white',
                        'cancelled' => 'bg-gray-400 text-white',
                        default => 'bg-gray-200 text-gray-500',
                    };
                @endphp
                <li class="flex-1 min-w-[5.5rem]">
                    <div @class(['rounded-lg border px-2 py-2 text-center text-xs', $stateClasses])>
                        <span @class(['inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold mb-1', $dotClasses])>
                            @if($step['state'] === 'completed') ✓ @else {{ $index + 1 }} @endif
                        </span>
                        <p class="font-semibold leading-tight">{{ $step['label'] }}</p>
                        @if($step['hint'])
                            <p class="text-[10px] mt-1 opacity-80 leading-tight">{{ $step['hint'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="border-t border-gray-100 px-6 py-5 space-y-5">
        @if($status !== \App\Enums\RentalStatus::Cancelado)
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Operação</p>
                <div class="flex flex-wrap gap-2">
                    @if($status === \App\Enums\RentalStatus::Reservado)
                        @can('operate', $rental)
                            <x-btn-primary wire:click="openCheckoutModal" class="text-sm">1. Registrar saída</x-btn-primary>
                        @endcan
                        @can('cancel', $rental)
                            <x-btn-secondary wire:click="openCancelModal" class="text-sm">Cancelar reserva</x-btn-secondary>
                        @endcan
                    @elseif($status === \App\Enums\RentalStatus::Locado)
                        @can('operate', $rental)
                            <x-btn-primary wire:click="openReturnModal" class="text-sm">2. Registrar retorno</x-btn-primary>
                            <x-btn-secondary wire:click="openExtendModal" class="text-sm">Prorrogar</x-btn-secondary>
                            <x-btn-secondary wire:click="openSubstituteModal" class="text-sm">Trocar equipamento</x-btn-secondary>
                        @endcan
                    @elseif($status === \App\Enums\RentalStatus::EmInspecao)
                        @can('operate', $rental)
                            <x-btn-primary wire:click="openCompleteModal" class="text-sm">3. Concluir inspeção</x-btn-primary>
                        @endcan
                    @elseif($status === \App\Enums\RentalStatus::Concluido)
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-sm text-emerald-800">Ficha encerrada</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Documentos</p>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('rentals.pdf', $rental) }}" target="_blank" class="flex items-center gap-2 text-indigo-600 hover:underline">
                        <span class="text-base">📄</span> Resumo / extrato da ficha
                    </a>
                    <a href="{{ route('rentals.contract.pdf', $rental) }}" target="_blank" class="flex items-center gap-2 text-indigo-600 hover:underline">
                        <span class="text-base">📋</span> Contrato de locação
                    </a>
                    <p class="flex items-center gap-2 text-gray-400 text-xs">
                        <span class="text-base">🚚</span> NF de remessa — previsto (integração fiscal)
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Financeiro</p>
                <div class="space-y-2 text-sm">
                    @php
                        $pendingBilling = $rental->billingQueueEntries->filter(
                            fn ($e) => in_array($e->status, ['pendente', 'autorizado'], true)
                        )->count();
                    @endphp
                    @if($canGenerateReceivables && $pendingBilling > 0)
                        @can('create', App\Models\Domain\Finance\ReceivableTitle::class)
                            <button type="button" wire:click="invoicePendingBilling" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                <span>💰</span> Gerar fatura agora
                                @if($pendingBilling > 1)
                                    <span class="rounded-full bg-white/20 px-1.5 text-xs">{{ $pendingBilling }}</span>
                                @endif
                            </button>
                        @endcan
                        <button type="button" wire:click="$set('activeTab', 'faturamento')" class="text-indigo-600 hover:underline text-xs">
                            Ver detalhes na aba Faturamento →
                        </button>
                        @can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)
                            <a href="{{ route('finance.billing-queue') }}" wire:navigate class="text-gray-500 hover:text-indigo-600 text-xs">Fila geral a faturar →</a>
                        @endcan
                    @elseif($rental->receivableTitles->isNotEmpty())
                        <p class="text-gray-600">{{ $rental->receivableTitles->count() }} título(s) gerado(s)</p>
                        @php $latestBilling = $rental->billingQueueEntries->firstWhere('status', 'faturado'); @endphp
                        @if($latestBilling)
                            <a href="{{ route('finance.billing.pdf', $latestBilling) }}" target="_blank" class="flex items-center gap-2 text-indigo-600 hover:underline">
                                <span>📄</span> Baixar última fatura (PDF)
                            </a>
                        @endif
                        @if($rental->receivableTitles->first()?->status === 'aberto')
                            @can('markPaid', $rental->receivableTitles->first())
                                <button type="button" wire:click="openBillingPayModal({{ $rental->receivableTitles->first()->id }})" class="text-emerald-600 hover:underline text-left">
                                    Registrar pagamento do título
                                </button>
                            @endcan
                        @endif
                        <button type="button" wire:click="$set('activeTab', 'faturamento')" class="text-indigo-600 hover:underline text-xs">
                            Ver faturamento na ficha →
                        </button>
                    @else
                        <p class="text-gray-500 text-xs">Informe o valor de faturamento e registre a saída para entrar na fila a faturar.</p>
                    @endif
                    @if($rental->next_billing_at)
                        <p class="text-xs text-gray-500">Próximo ciclo: <strong>{{ $rental->next_billing_at->format('d/m/Y') }}</strong></p>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Manutenção / OS</p>
                <div class="space-y-2 text-sm">
                    @if($canOpenMaintenanceOrder)
                        @can('create', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                            <button type="button" wire:click="openMaintenanceOrderModal" class="flex items-center gap-2 text-indigo-600 hover:underline">
                                <span class="text-base">🔧</span> Abrir ordem de serviço
                            </button>
                        @endcan
                    @elseif($rental->maintenanceOrders->where(fn ($o) => $o->statusEnum()->isOpen())->isNotEmpty())
                        <p class="text-amber-700 text-xs">Já existe OS aberta para esta ficha.</p>
                    @endif
                    @forelse($rental->maintenanceOrders as $order)
                        <a href="{{ route('maintenance.show', $order) }}" wire:navigate class="block text-indigo-600 hover:underline">
                            {{ $order->codigo }} — {{ $order->tipoEnum()->label() }}
                            <span class="text-gray-500">({{ $order->statusEnum()->label() }})</span>
                        </a>
                    @empty
                        <p class="text-gray-500 text-xs">Nenhuma OS vinculada. Abra na inspeção ou manualmente.</p>
                    @endforelse
                    <a href="{{ route('maintenance.index') }}" wire:navigate class="text-xs text-gray-500 hover:text-indigo-600">Ver todas as OS →</a>
                </div>
            </div>
        </div>
    </div>
</div>
