<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $rental->codigo }} — Demonstrativo</title>
    @include('documents.partials.styles')
</head>
<body>
<div class="page">
    @include('documents.partials.company-header', [
        'documentTitle' => 'DEMONSTRATIVO DE LOCAÇÃO',
        'documentCode' => $rental->codigo,
        'documentBadge' => $periodStart->format('d/m/Y').' — '.$periodEnd->format('d/m/Y'),
    ])

    <div class="section">
        <div class="section-title">Período do demonstrativo</div>
        <table class="info-grid">
            <tr>
                <td class="label">De</td>
                <td class="value">{{ $periodStart->format('d/m/Y') }}</td>
                <td class="label">Até</td>
                <td class="value">{{ $periodEnd->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Dias no período</td>
                <td class="value">{{ $billedDays }}</td>
                <td class="label">Gerado em</td>
                <td class="value">{{ $generatedAt->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Locação</div>
        <table class="info-grid">
            <tr>
                <td class="label">Cliente</td>
                <td class="value">{{ $rental->customer->nome }}</td>
                <td class="label">Patrimônio</td>
                <td class="value">{{ $rental->asset->codigo_patrimonio }}</td>
            </tr>
            <tr>
                <td class="label">Equipamento</td>
                <td class="value" colspan="3">{{ $rental->asset->equipmentModel->displayName() }}</td>
            </tr>
            @if($rental->local_obra)
            <tr>
                <td class="label">Local da obra</td>
                <td class="value" colspan="3">{{ $rental->local_obra }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Valores estimados no período</div>
        <table class="info-grid">
            <tr>
                <td class="label">Valor acordado (ficha)</td>
                <td class="value">
                    @if($rental->valor_faturamento)
                        R$ {{ number_format($rental->valor_faturamento, 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td class="label">Ciclo de faturamento</td>
                <td class="value">{{ $rental->billing_cycle_days ? $rental->billing_cycle_days.' dias' : '—' }}</td>
            </tr>
            @if($pricing)
            <tr>
                <td class="label">Cálculo pela tabela</td>
                <td class="value" colspan="3">
                    {{ $pricing['breakdown'] }} = <strong>R$ {{ number_format($pricing['valor_calculado'], 2, ',', '.') }}</strong>
                </td>
            </tr>
            @else
            <tr>
                <td class="label">Cálculo pela tabela</td>
                <td class="value" colspan="3">Sem tabela de preços aplicável para este período.</td>
            </tr>
            @endif
        </table>
        <p style="font-size: 9px; color: #666; margin-top: 8px;">
            Valores estimados com base na tabela de preços e dias corridos do período informado.
            Pendências e faturas efetivas constam na tabela abaixo.
        </p>
    </div>

    <div class="section">
        <div class="section-title">Pendências e faturas no período</div>
        @if($billingEntries->isEmpty())
            <p class="text-block" style="font-size: 11px;">Nenhuma pendência ou fatura registrada para este intervalo.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Período</th>
                        <th class="num">Valor NF</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billingEntries as $entry)
                        <tr>
                            <td>{{ $entry->codigo }}</td>
                            <td>{{ $entry->tipoEnum()->label() }}</td>
                            <td>
                                @if($entry->periodo_inicio && $entry->periodo_fim)
                                    {{ $entry->periodo_inicio->format('d/m/Y') }} — {{ $entry->periodo_fim->format('d/m/Y') }}
                                @else
                                    {{ $entry->gerado_em?->format('d/m/Y') ?? '—' }}
                                @endif
                            </td>
                            <td class="num">R$ {{ number_format($entry->valor_nf, 2, ',', '.') }}</td>
                            <td>{{ $entry->statusEnum()->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($rental->contrato_clausula_prorata)
        <div class="section">
            <div class="section-title">Observação contratual</div>
            <p style="font-size: 10px; text-align: justify; line-height: 1.45; color: #444;">
                Esta locação está sujeita à cláusula de prorrogação automática e cobrança pro-rata
                após o prazo previsto, quando o locatário não solicitar a devolução do equipamento.
            </p>
        </div>
    @endif

    <div class="footer">
        Documento gerado em {{ $generatedAt->format('d/m/Y H:i') }} — {{ \App\Support\BrandContext::documentFooter($company) }}
    </div>
</div>
</body>
</html>
