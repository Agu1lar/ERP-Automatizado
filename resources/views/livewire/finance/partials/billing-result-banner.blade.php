@php
    $billingInvoiceMethod = $billingInvoiceMethod ?? 'invoiceEntry';
    $billingShowQueueNav = $billingShowQueueNav ?? true;
@endphp

@if($highlightBillingEntry)
    @php
        $entryStatus = $highlightBillingEntry->statusEnum();
        $canInvoice = auth()->user()->can('create', App\Models\Domain\Finance\ReceivableTitle::class);
    @endphp
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 space-y-3">
        <div>
            <p class="font-semibold text-emerald-900">
                @if($entryStatus === \App\Enums\RentalBillingQueueStatus::Faturado)
                    Fatura {{ $highlightBillingEntry->codigo }} gerada com sucesso
                @elseif($entryStatus === \App\Enums\RentalBillingQueueStatus::Autorizado)
                    {{ $highlightBillingEntry->codigo }} autorizada — pronto para faturar
                @else
                    {{ $highlightBillingEntry->codigo }}
                @endif
            </p>
            <p class="text-sm text-emerald-800 mt-1">
                @if($entryStatus === \App\Enums\RentalBillingQueueStatus::Autorizado)
                    Confira os dados abaixo e gere a fatura, ou use os atalhos para continuar sem sair desta página.
                @elseif($entryStatus === \App\Enums\RentalBillingQueueStatus::Faturado)
                    Baixe o PDF, registre o pagamento quando receber, ou abra a ficha da locação.
                @else
                    Acompanhe o próximo passo pelos atalhos abaixo.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($canInvoice && $entryStatus === \App\Enums\RentalBillingQueueStatus::Autorizado)
                <button
                    type="button"
                    wire:click="{{ $billingInvoiceMethod }}({{ $highlightBillingEntry->id }})"
                    class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    Gerar fatura agora
                </button>
            @endif

            @if($entryStatus === \App\Enums\RentalBillingQueueStatus::Autorizado)
                @if($billingShowQueueNav)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'autorizado')"
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
                    >
                        Ver fila autorizada
                    </button>
                @else
                    <a
                        href="{{ route('finance.billing-queue', ['status' => 'autorizado']) }}"
                        wire:navigate
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
                    >
                        Ver fila autorizada
                    </a>
                @endif
            @endif

            @if($entryStatus === \App\Enums\RentalBillingQueueStatus::Faturado)
                @if($billingShowQueueNav)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'faturado')"
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
                    >
                        Ver faturamentos
                    </button>
                @else
                    <a
                        href="{{ route('finance.billing-queue', ['status' => 'faturado']) }}"
                        wire:navigate
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
                    >
                        Ver faturamentos
                    </a>
                @endif
            @endif
        </div>

        <x-billing-entry-actions :entry="$highlightBillingEntry" />

        <div class="flex flex-wrap gap-3 text-xs pt-1 border-t border-emerald-200/80">
            @if($highlightBillingEntry->rental)
                <a href="{{ route('rentals.show', $highlightBillingEntry->rental) }}" wire:navigate class="text-indigo-700 hover:underline">
                    Abrir ficha {{ $highlightBillingEntry->rental->codigo }}
                </a>
            @endif
            @if($highlightBillingEntry->receivableTitle)
                <a href="{{ route('finance.receivables', ['q' => $highlightBillingEntry->receivableTitle->codigo]) }}" wire:navigate class="text-indigo-700 hover:underline">
                    Ver título no financeiro
                </a>
            @endif
            <button type="button" wire:click="dismissBillingHighlight" class="text-gray-500 hover:text-gray-700">Fechar painel</button>
        </div>
    </div>
@endif
