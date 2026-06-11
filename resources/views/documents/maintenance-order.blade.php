<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $order->codigo }} — Ordem de Manutenção</title>
    <style>
        @page {
            size: 210mm 99mm;
            margin: 2.5mm 3mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7px;
            color: #111;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .os-slip {
            width: 100%;
            height: 94mm;
            overflow: hidden;
        }
        .os-header { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .os-header td { vertical-align: middle; padding: 0; }
        .os-logo { max-height: 22px; max-width: 70px; }
        .os-title {
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .os-type { font-size: 7px; margin-top: 1px; text-align: center; }
        .os-type span { margin: 0 6px; }
        .os-code { font-size: 7px; text-align: right; color: #333; }
        .os-equip {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5px;
            margin-bottom: 2px;
        }
        .os-equip td {
            border: 1px solid #333;
            padding: 1.5px 3px;
            vertical-align: top;
        }
        .os-equip .lbl { font-weight: bold; width: 16%; background: #f5f5f5; }
        .os-problem {
            border: 1px solid #333;
            padding: 1.5px 3px;
            font-size: 6.5px;
            margin-bottom: 2px;
        }
        .os-parts {
            width: 100%;
            border-collapse: collapse;
            font-size: 6px;
        }
        .os-parts th, .os-parts td {
            border: 1px solid #333;
            padding: 1px 2px;
        }
        .os-parts th { background: #f0f0f0; font-weight: bold; text-align: center; }
        .os-parts .num { text-align: right; }
        .os-parts td { height: 9px; }
        .os-parecer-wrap { margin-top: 2px; }
        .os-parecer-label { font-size: 6.5px; font-weight: bold; }
        .os-parecer {
            border: 1px solid #333;
            min-height: 18px;
            max-height: 22px;
            overflow: hidden;
            padding: 2px 3px;
            font-size: 6px;
            white-space: pre-wrap;
        }
        .os-signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 6px;
        }
        .os-signatures td {
            width: 33%;
            text-align: center;
            padding-top: 10px;
            vertical-align: bottom;
        }
        .os-sign-line {
            border-top: 1px solid #333;
            margin: 0 4px;
            padding-top: 1px;
        }
        .os-total { text-align: right; font-size: 6px; margin-top: 1px; }
        .os-extra { font-size: 6px; margin-top: 1px; }
    </style>
</head>
<body>
@php
    $asset = $order->asset;
    $model = $asset->equipmentModel;
    $customer = $order->resolvedCustomer();
    $isIndenizacao = $order->tipoEnum()->isIndenizacao();
    $parts = $order->parts;
    $maxPartRows = 5;
    $emptyPartRows = max(0, $maxPartRows - $parts->count());
    $parecer = $order->parecerTecnicoText();
@endphp
<div class="os-slip">
    <table class="os-header">
        <tr>
            <td style="width: 22%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo" class="os-logo">
                @else
                    <strong style="font-size: 7px;">{{ \App\Support\BrandContext::documentFooter($company) }}</strong>
                @endif
            </td>
            <td style="width: 56%;">
                <div class="os-title">ORDEM DE MANUTENÇÃO</div>
                <div class="os-type">
                    <span>{{ $isIndenizacao ? '☐' : '☑' }} MANUTENÇÃO</span>
                    <span>{{ $isIndenizacao ? '☑' : '☐' }} INDENIZAÇÃO</span>
                </div>
            </td>
            <td style="width: 22%;" class="os-code">
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
        <div class="os-problem"><strong>Problema:</strong> {{ \Illuminate\Support\Str::limit($order->descricao_problema, 120) }}</div>
    @endif

    <table class="os-parts">
        <thead>
            <tr>
                <th style="width: 8%;">QTD</th>
                <th style="width: 14%;">CÓDIGO</th>
                <th style="width: 38%;">DESCRIÇÃO</th>
                <th style="width: 22%;">CÓD. ALT.</th>
                <th style="width: 12%;">VALOR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parts->take($maxPartRows) as $part)
                <tr>
                    <td class="num">{{ number_format($part->quantidade, 2, ',', '.') }}</td>
                    <td>{{ $part->codigo_peca ?? '' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($part->descricao, 40) }}</td>
                    <td>{{ $part->codigo_alternativo ?? '' }}</td>
                    <td class="num">{{ $part->valor_unitario !== null ? number_format($part->subtotal(), 2, ',', '.') : '' }}</td>
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
        <div class="os-total">
            <strong>Total peças: R$ {{ number_format($order->totalPartsCost(), 2, ',', '.') }}</strong>
        </div>
    @endif

    <div class="os-parecer-wrap">
        <div class="os-parecer-label">PARECER TÉCNICO:</div>
        <div class="os-parecer">{{ $parecer !== '' ? \Illuminate\Support\Str::limit($parecer, 220) : ' ' }}</div>
    </div>

    @if(!empty($customFieldRows))
        <div class="os-extra">
            @foreach(array_slice($customFieldRows, 0, 2) as $row)
                {{ $row['label'] }}: {{ \Illuminate\Support\Str::limit((string) $row['value'], 40) }}@if(!$loop->last) | @endif
            @endforeach
        </div>
    @endif

    <table class="os-signatures">
        <tr>
            <td><div class="os-sign-line">caixa: {{ $order->assinatura_caixa ?? '' }}</div></td>
            <td><div class="os-sign-line">orçado por: {{ $order->assinatura_orcado_por ?? '' }}</div></td>
            <td><div class="os-sign-line">montado por: {{ $order->assinatura_montado_por ?? '' }}</div></td>
        </tr>
    </table>
</div>
</body>
</html>
