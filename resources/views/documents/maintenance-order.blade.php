<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $order->codigo }} — Ordem de Manutenção</title>
    @include('documents.partials.styles')
    <style>
        .os-header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .os-header td { vertical-align: middle; }
        .os-logo { max-height: 70px; max-width: 180px; }
        .os-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .os-type { font-size: 11px; margin-top: 4px; }
        .os-type span { margin: 0 12px; }
        .os-box {
            border: 1px solid #333;
            padding: 6px 8px;
            margin-bottom: 8px;
        }
        .os-equip {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .os-equip td {
            border: 1px solid #333;
            padding: 5px 6px;
            vertical-align: top;
        }
        .os-equip .lbl { font-weight: bold; width: 18%; background: #f5f5f5; }
        .os-parts {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .os-parts th, .os-parts td {
            border: 1px solid #333;
            padding: 4px 5px;
        }
        .os-parts th { background: #f0f0f0; font-weight: bold; text-align: center; }
        .os-parts .num { text-align: right; }
        .os-parecer {
            border: 1px solid #333;
            min-height: 90px;
            padding: 8px;
            font-size: 10px;
            white-space: pre-wrap;
        }
        .os-signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 9px;
        }
        .os-signatures td {
            width: 33%;
            text-align: center;
            padding-top: 28px;
            vertical-align: bottom;
        }
        .os-sign-line {
            border-top: 1px solid #333;
            margin: 0 8px;
            padding-top: 4px;
        }
        .os-code { font-size: 10px; text-align: right; color: #444; }
    </style>
</head>
<body>
@php
    $asset = $order->asset;
    $model = $asset->equipmentModel;
    $customer = $order->resolvedCustomer();
    $isIndenizacao = $order->tipoEnum()->isIndenizacao();
    $parts = $order->parts;
    $emptyPartRows = max(0, 12 - $parts->count());
    $parecer = $order->parecerTecnicoText();
@endphp
<div class="page">
    <table class="os-header">
        <tr>
            <td style="width: 25%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo" class="os-logo">
                @else
                    <p class="company-name" style="font-size: 14px;">{{ $company['name'] ?? 'Linha Leve' }}</p>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="os-title">ORDEM DE MANUTENÇÃO</div>
                <div class="os-type">
                    <span>{{ $isIndenizacao ? '☐' : '☑' }} MANUTENÇÃO</span>
                    <span>{{ $isIndenizacao ? '☑' : '☐' }} INDENIZAÇÃO</span>
                </div>
            </td>
            <td style="width: 25%;" class="os-code">
                <strong>{{ $order->codigo }}</strong><br>
                {{ $order->opened_at->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <table class="os-equip">
        <tr>
            <td class="lbl">EQUP:</td>
            <td>{{ $model->displayName() }}</td>
            <td class="lbl">MARCA:</td>
            <td>{{ $model->marca }}</td>
        </tr>
        <tr>
            <td class="lbl">PATRIMÔNIO:</td>
            <td>{{ $asset->codigo_patrimonio }}</td>
            <td class="lbl">VOLTAGEM:</td>
            <td>{{ $asset->voltagem ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">CLIENTE:</td>
            <td colspan="3">{{ $customer?->nome ?? '—' }}</td>
        </tr>
    </table>

    @if(filled($order->descricao_problema))
        <div class="os-box" style="margin-top: 8px; font-size: 10px;">
            <strong>Problema relatado:</strong> {{ $order->descricao_problema }}
        </div>
    @endif

    <table class="os-parts" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 8%;">QUANT</th>
                <th style="width: 16%;">CÓDIGO DA PEÇA</th>
                <th style="width: 36%;">DESCRIÇÃO</th>
                <th style="width: 20%;">CÓDIGO ALTERNATIVO</th>
                <th style="width: 12%;">VALOR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parts as $part)
                <tr>
                    <td class="num">{{ number_format($part->quantidade, 2, ',', '.') }}</td>
                    <td>{{ $part->codigo_peca ?? '' }}</td>
                    <td>{{ $part->descricao }}</td>
                    <td>{{ $part->codigo_alternativo ?? '' }}</td>
                    <td class="num">{{ $part->valor_unitario !== null ? 'R$ '.number_format($part->subtotal(), 2, ',', '.') : '' }}</td>
                </tr>
            @endforeach
            @for($i = 0; $i < $emptyPartRows; $i++)
                <tr>
                    <td>&nbsp;</td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
    </table>
    @if($parts->isNotEmpty())
        <div style="text-align: right; font-size: 10px; margin-top: 4px;">
            <strong>Total peças: R$ {{ number_format($order->totalPartsCost(), 2, ',', '.') }}</strong>
        </div>
    @endif

    <div style="margin-top: 12px; font-size: 10px; font-weight: bold;">PARECER TÉCNICO:</div>
    <div class="os-parecer">{{ $parecer !== '' ? $parecer : ' ' }}</div>

    @if(!empty($customFieldRows))
        <div style="margin-top: 10px; font-size: 10px;">
            <strong>Informações adicionais</strong>
            @foreach($customFieldRows as $row)
                <div>{{ $row['label'] }}: {{ $row['value'] }}</div>
            @endforeach
        </div>
    @endif

    <table class="os-signatures">
        <tr>
            <td>
                <div class="os-sign-line">caixa: {{ $order->assinatura_caixa ?? '' }}</div>
            </td>
            <td>
                <div class="os-sign-line">orçado por: {{ $order->assinatura_orcado_por ?? '' }}</div>
            </td>
            <td>
                <div class="os-sign-line">montado por: {{ $order->assinatura_montado_por ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company['name'] ?? 'Linha Leve' }}
        @if(!empty($company['phone'])) — {{ $company['phone'] }}@endif
        | Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
        @if($order->completed_at) | Concluída em {{ $order->completed_at->format('d/m/Y') }}@endif
    </div>
</div>
</body>
</html>
