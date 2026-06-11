<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $rental->codigo }} — Contrato</title>
    @include('documents.partials.styles')
    <style>
        .clause { margin-bottom: 10px; text-align: justify; font-size: 11px; line-height: 1.45; }
        .clause-num { font-weight: bold; }
        .signature-block { margin-top: 40px; }
        .signature-line { border-top: 1px solid #333; width: 45%; display: inline-block; margin-top: 48px; padding-top: 6px; font-size: 11px; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    @include('documents.partials.company-header', [
        'documentTitle' => 'CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS',
        'documentCode' => $rental->codigo,
        'documentBadge' => $rental->statusEnum()->label(),
    ])

    <div class="section">
        <div class="section-title">Partes</div>
        <table class="info-grid">
            <tr>
                <td class="label">LOCADOR</td>
                <td class="value" colspan="3">{{ $company['name'] ?? config('documents.company.name') }}</td>
            </tr>
            @if(!empty($company['document']))
            <tr>
                <td class="label">CNPJ do locador</td>
                <td class="value" colspan="3">{{ $company['document'] }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">LOCATÁRIO</td>
                <td class="value">{{ $rental->customer->nome }}</td>
                <td class="label">CPF/CNPJ</td>
                <td class="value">{{ $rental->customer->formattedDocument() }}</td>
            </tr>
            @if($rental->customer->endereco)
            <tr>
                <td class="label">Endereço</td>
                <td class="value" colspan="3">{{ $rental->customer->endereco }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Objeto da locação</div>
        <table class="info-grid">
            <tr>
                <td class="label">Patrimônio</td>
                <td class="value">{{ $rental->asset->codigo_patrimonio }}</td>
                <td class="label">Equipamento</td>
                <td class="value">{{ $rental->asset->equipmentModel->displayName() }}</td>
            </tr>
            <tr>
                <td class="label">Categoria</td>
                <td class="value">{{ $rental->asset->equipmentModel->category->nome }}</td>
                <td class="label">Responsável comercial</td>
                <td class="value">{{ $rental->commercialUser?->name ?? '—' }}</td>
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
        <div class="section-title">Condições comerciais</div>
        <table class="info-grid">
            <tr>
                <td class="label">Período</td>
                <td class="value">
                    @if($rental->checkout_at)
                        Saída: {{ $rental->checkout_at->format('d/m/Y') }}
                    @else
                        Reserva: {{ $rental->reserved_at->format('d/m/Y') }}
                    @endif
                    @if($rental->expected_return_at)
                        — Retorno previsto: {{ $rental->expected_return_at->format('d/m/Y') }}
                    @endif
                </td>
                <td class="label">Valor da locação</td>
                <td class="value">
                    @if($rental->valor_faturamento)
                        R$ {{ number_format($rental->valor_faturamento, 2, ',', '.') }}
                    @else
                        A combinar / conforme tabela
                    @endif
                </td>
            </tr>
            @if($rental->pricing_period)
            <tr>
                <td class="label">Tabela</td>
                <td class="value" colspan="3">{{ \App\Enums\RentalPricingPeriod::from($rental->pricing_period)->label() }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($rental->assetSubstitutions->isNotEmpty())
        <div class="section">
            <div class="section-title">Substituições de equipamento</div>
            @foreach($rental->assetSubstitutions as $sub)
                <p class="clause">
                    {{ $sub->substituted_at->format('d/m/Y H:i') }}:
                    {{ $sub->fromAsset->codigo_patrimonio }} → {{ $sub->toAsset->codigo_patrimonio }}
                    @if($sub->motivo) — {{ $sub->motivo }} @endif
                </p>
            @endforeach
        </div>
    @endif

    <div class="section">
        <div class="section-title">Cláusulas contratuais</div>
        @foreach($clauses as $index => $clause)
            <p class="clause"><span class="clause-num">{{ $index + 1 }}.</span> {{ $clause }}</p>
        @endforeach
    </div>

    <div class="signature-block">
        <span class="signature-line">{{ $company['name'] ?? config('documents.company.name') }}<br>LOCADOR</span>
        <span style="display:inline-block; width:8%;"></span>
        <span class="signature-line">{{ $rental->customer->nome }}<br>LOCATÁRIO</span>
    </div>

    <p style="font-size:9px; color:#666; margin-top:24px;">
        Documento gerado em {{ $generatedAt->format('d/m/Y H:i') }} — {{ $rental->codigo }}
    </p>
</div>
</body>
</html>
