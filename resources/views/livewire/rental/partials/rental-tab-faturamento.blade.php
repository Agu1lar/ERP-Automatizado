@php

    $canEditFicha = auth()->user()->can('updateFicha', $rental);

    $canInvoice = auth()->user()->can('create', App\Models\Domain\Finance\ReceivableTitle::class);

@endphp



<div class="space-y-6">

    @include('livewire.finance.partials.billing-result-banner')



    <div class="bg-white rounded-lg shadow p-6">

        <h3 class="font-semibold text-gray-800 mb-4">Configuração do ciclo</h3>

        @if($canEditFicha && $rental->checkout_at)

            <form wire:submit="saveBillingSettings" class="grid md:grid-cols-3 gap-4 items-end">

                <div>

                    <label class="block text-sm font-medium text-gray-700">Ciclo de faturamento (dias)</label>

                    <input wire:model="billing_cycle_days" type="number" min="1" max="365" class="mt-1 w-full rounded-md border-gray-300 text-sm" />

                    @error('billing_cycle_days') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                </div>

                <div>

                    <label class="block text-sm font-medium text-gray-700">Valor mínimo por período (opcional)</label>

                    <input wire:model="billing_min_amount" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="0,00" />

                    @error('billing_min_amount') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                </div>

                <x-btn-primary type="submit" class="text-sm">Salvar ciclo</x-btn-primary>

            </form>

        @else

            <dl class="grid md:grid-cols-3 gap-4 text-sm">

                <div><dt class="text-gray-500">Ciclo</dt><dd class="font-medium">{{ $rental->billing_cycle_days ?? 28 }} dias</dd></div>

                <div><dt class="text-gray-500">Valor mínimo</dt><dd class="font-medium">{{ $rental->billing_min_amount ? 'R$ '.number_format($rental->billing_min_amount, 2, ',', '.') : '—' }}</dd></div>

                <div><dt class="text-gray-500">Próximo faturamento</dt><dd class="font-medium">{{ $rental->next_billing_at?->format('d/m/Y') ?? '—' }}</dd></div>

            </dl>

        @endif



        @if($rental->billing_period_start && $rental->billing_period_end)

            <p class="text-sm text-gray-500 mt-4">

                Período atual: <strong>{{ $rental->billing_period_start->format('d/m/Y') }}</strong>

                a <strong>{{ $rental->billing_period_end->format('d/m/Y') }}</strong>

                @if($rental->last_billed_at)

                    · Último faturamento: {{ $rental->last_billed_at->format('d/m/Y') }}

                @endif

            </p>

        @endif



        @if($canEditFicha && $rental->statusEnum() === \App\Enums\RentalStatus::Locado)

            <div class="mt-4 pt-4 border-t border-gray-100">

                <x-btn-secondary wire:click="generateRenewalBilling" class="text-sm">Gerar renovação agora (se vencida)</x-btn-secondary>

            </div>

        @endif

    </div>



    <div class="bg-white rounded-lg shadow p-6">

        <div class="flex flex-wrap justify-between items-center gap-2 mb-4">

            <h3 class="font-semibold text-gray-800">Fila de faturamento desta ficha</h3>

            @can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)

                <a href="{{ route('finance.billing-queue') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">

                    Ver todas a faturar ({{ $pendingBillingCount }}) →

                </a>

            @endcan

        </div>



        @if($rental->billingQueueEntries->isEmpty())

            <p class="text-sm text-gray-500">Nenhuma pendência de faturamento. A saída com valor gera automaticamente a primeira entrada na fila.</p>

        @else

            <table class="min-w-full text-sm">

                <thead>

                    <tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">

                        <th class="pb-2 pr-4">Código</th>

                        <th class="pb-2 pr-4">Tipo</th>

                        <th class="pb-2 pr-4">Período</th>

                        <th class="pb-2 pr-4 text-right">Valor CAR</th>

                        <th class="pb-2 pr-4">Status</th>

                        <th class="pb-2 pr-4">Vencimento título</th>

                        <th class="pb-2">Ações</th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-gray-50">

                    @foreach($rental->billingQueueEntries as $entry)

                        <tr>

                            <td class="py-3 pr-4 font-medium">{{ $entry->codigo }}</td>

                            <td class="py-3 pr-4">{{ $entry->tipoEnum()->label() }}</td>

                            <td class="py-3 pr-4">

                                @if($entry->periodo_inicio && $entry->periodo_fim)

                                    {{ $entry->periodo_inicio->format('d/m/Y') }} — {{ $entry->periodo_fim->format('d/m/Y') }}

                                @else

                                    —

                                @endif

                            </td>

                            <td class="py-3 pr-4 text-right">R$ {{ number_format($entry->valor_car, 2, ',', '.') }}</td>

                            <td class="py-3 pr-4"><x-status-badge :status="$entry->statusEnum()" /></td>

                            <td class="py-3 pr-4">
                                @if($canInvoice && $entry->receivableTitle && in_array($entry->statusEnum(), [\App\Enums\RentalBillingQueueStatus::Pendente, \App\Enums\RentalBillingQueueStatus::Autorizado], true))
                                    <div class="flex items-center gap-2">
                                        <input
                                            wire:model="billing_title_vencimento.{{ $entry->id }}"
                                            type="date"
                                            class="rounded-md border-gray-300 text-xs"
                                        />
                                        <button
                                            type="button"
                                            wire:click="saveBillingTitleDueDate({{ $entry->id }})"
                                            class="text-indigo-600 hover:underline text-xs whitespace-nowrap"
                                        >
                                            Salvar
                                        </button>
                                    </div>
                                    @error("billing_title_vencimento.{$entry->id}") <span class="text-red-600 text-xs block">{{ $message }}</span> @enderror
                                @elseif($entry->receivableTitle)
                                    {{ $entry->receivableTitle->vencimento->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="py-3">

                                <div class="flex flex-col gap-2 items-start">

                                    @if($canInvoice)

                                        @if($entry->statusEnum() === \App\Enums\RentalBillingQueueStatus::Pendente)

                                            <div class="space-x-2">

                                                <button wire:click="authorizeBillingEntry({{ $entry->id }})" class="text-indigo-600 hover:underline text-xs">Autorizar</button>

                                                <button wire:click="invoiceBillingEntry({{ $entry->id }})" class="text-emerald-600 hover:underline text-xs font-medium">Gerar fatura</button>

                                            </div>

                                        @elseif($entry->statusEnum() === \App\Enums\RentalBillingQueueStatus::Autorizado)

                                            <button wire:click="invoiceBillingEntry({{ $entry->id }})" class="text-emerald-600 hover:underline text-xs font-medium">Gerar fatura</button>

                                        @endif

                                    @endif

                                    <x-billing-entry-actions :entry="$entry" :compact="true" />

                                </div>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        @endif

    </div>



    @can('viewAny', App\Models\Domain\Finance\ReceivableTitle::class)

        @if($rental->receivableTitles->isNotEmpty())

            <div class="bg-white rounded-lg shadow p-6">

                <h3 class="font-semibold text-gray-800 mb-4">Títulos a receber gerados</h3>

                <table class="min-w-full text-sm">

                    <thead>

                        <tr class="text-left text-xs text-gray-500 uppercase">

                            <th class="pb-2">Código</th>

                            <th class="pb-2">Parcela</th>

                            <th class="pb-2 text-right">Valor</th>

                            <th class="pb-2">Vencimento</th>

                            <th class="pb-2">Status</th>

                            <th class="pb-2">Ações</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-100">

                        @foreach($rental->receivableTitles as $title)

                            <tr @class(['text-red-700' => $title->isOverdue()])>

                                <td class="py-2">{{ $title->codigo }}</td>

                                <td class="py-2">{{ $title->parcelLabel() }}</td>

                                <td class="py-2 text-right">R$ {{ number_format($title->valor, 2, ',', '.') }}</td>

                                <td class="py-2">{{ $title->vencimento->format('d/m/Y') }}</td>

                                <td class="py-2">{{ $title->statusEnum()->label() }}</td>

                                <td class="py-2">

                                    <div class="flex flex-wrap gap-2 items-center">

                                        <a href="{{ route('finance.receivable.export', $title) }}" target="_blank" class="text-indigo-600 hover:underline text-xs">Baixar</a>

                                        @if($title->status === 'aberto')

                                            @can('markPaid', $title)

                                                <button type="button" wire:click="openBillingPayModal({{ $title->id }})" class="text-emerald-600 hover:underline text-xs font-medium">Registrar pagamento</button>

                                            @endcan

                                        @elseif($title->status === 'pago')

                                            <span class="text-xs text-gray-500">{{ $title->pago_em?->format('d/m/Y') }}</span>

                                        @endif

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    @endcan

</div>


