<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $entry->codigo }} — Fatura</title>
    @include('documents.partials.styles')
</head>
<body>
<div class="page">
    @include('documents.partials.company-header', [
        'documentTitle' => 'FATURA DE LOCAÇÃO',
        'documentCode' => $entry->codigo,
        'documentBadge' => $entry->statusEnum()->label(),
    ])

    <div class="section">
        <div class="section-title">Destinatário</div>
        <table class="info-grid">
            <tr>
                <td class="label">Cliente</td>
                <td class="value" colspan="3">{{ $entry->customer->nome }}</td>
            </tr>
            <tr>
                <td class="label">CPF/CNPJ</td>
                <td class="value">{{ $entry->customer->formattedDocument() }}</td>
                <td class="label">Telefone</td>
                <td class="value">{{ $entry->customer->telefone ?? '—' }}</td>
            </tr>
            @if($entry->customer->email)
            <tr>
                <td class="label">E-mail</td>
                <td class="value" colspan="3">{{ $entry->customer->email }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Referência da locação</div>
        <table class="info-grid">
            <tr>
                <td class="label">Locação</td>
                <td class="value">{{ $entry->rental?->codigo ?? '—' }}</td>
                <td class="label">Tipo</td>
                <td class="value">{{ $entry->tipoEnum()->label() }}</td>
            </tr>
            <tr>
                <td class="label">Patrimônio</td>
                <td class="value">{{ $entry->rental?->asset?->codigo_patrimonio ?? '—' }}</td>
                <td class="label">Equipamento</td>
                <td class="value">{{ $entry->rental?->asset?->equipmentDisplayName() ?? '—' }}</td>
            </tr>
            @if($entry->rental?->local_obra)
            <tr>
                <td class="label">Local da obra</td>
                <td class="value" colspan="3">{{ $entry->rental->local_obra }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Valores e período</div>
        <table class="info-grid">
            <tr>
                <td class="label">Período</td>
                <td class="value" colspan="3">
                    @if($entry->periodo_inicio && $entry->periodo_fim)
                        {{ $entry->periodo_inicio->format('d/m/Y') }} a {{ $entry->periodo_fim->format('d/m/Y') }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Valor NF</td>
                <td class="value"><strong>R$ {{ number_format($entry->valor_nf, 2, ',', '.') }}</strong></td>
                <td class="label">Valor a receber</td>
                <td class="value"><strong>R$ {{ number_format($entry->valor_car, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    @if($entry->receivableTitle)
    <div class="section">
        <div class="section-title">Cobrança</div>
        <table class="info-grid">
            <tr>
                <td class="label">Título</td>
                <td class="value">{{ $entry->receivableTitle->codigo }}</td>
                <td class="label">Vencimento</td>
                <td class="value">{{ $entry->receivableTitle->vencimento->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">{{ $entry->receivableTitle->statusEnum()->label() }}</td>
                <td class="label">Pago em</td>
                <td class="value">{{ $entry->receivableTitle->pago_em?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        </table>
    </div>
    @endif

    @if($entry->observacoes)
    <div class="section">
        <div class="section-title">Observações</div>
        <p style="font-size: 11px; margin: 0;">{{ $entry->observacoes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Documento gerado em {{ $generatedAt->format('d/m/Y H:i') }}
            @if($entry->faturado_em)
                · Faturado em {{ $entry->faturado_em->format('d/m/Y H:i') }}
            @endif
        </p>
        <p style="font-size: 9px; color: #666;">Este documento é um comprovante interno de faturamento. A nota fiscal eletrônica, quando aplicável, deve ser emitida pelo sistema fiscal da empresa.</p>
    </div>
</div>
</body>
</html>
