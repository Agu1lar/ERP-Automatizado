@props(['entry', 'compact' => false])

@php
    $title = $entry->receivableTitle;
    $pdfUrl = route('finance.billing.pdf', $entry);
    $csvUrl = route('finance.billing.export', $entry);
    $titleExportUrl = $title ? route('finance.receivable.export', $title) : null;
@endphp

<div @class([
    'flex flex-wrap items-center gap-2',
    'text-xs' => $compact,
])>
    <a
        href="{{ $pdfUrl }}"
        target="_blank"
        class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-white hover:bg-indigo-700 font-medium"
        title="Fatura em PDF para arquivo ou envio ao cliente"
    >
        <span aria-hidden="true">📄</span> Baixar fatura (PDF)
    </a>

    <a
        href="{{ $csvUrl }}"
        target="_blank"
        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-gray-600 hover:bg-gray-50"
        title="Planilha para importação contábil (Sisloc/ERP)"
    >
        CSV contábil
    </a>

    @if($title)
        @if($title->status === 'aberto')
            @can('markPaid', $title)
                <button
                    type="button"
                    wire:click="openBillingPayModal({{ $title->id }})"
                    class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1.5 text-white hover:bg-emerald-700 font-medium"
                >
                    Registrar pagamento
                </button>
            @endcan
        @elseif($title->status === 'pago')
            <span class="text-emerald-700 text-xs">Pago em {{ $title->pago_em?->format('d/m/Y') }}</span>
        @endif
    @endif
</div>
