<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha {{ $asset->codigo_patrimonio }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .muted { color: #666; font-size: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 160px; gap: 24px; margin-top: 24px; }
        .box { border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
        .row { margin-bottom: 8px; font-size: 14px; }
        .label { color: #666; }
        img { width: 140px; height: 140px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Imprimir</button>

    <h1>{{ $asset->codigo_patrimonio }}</h1>
    <p class="muted">{{ $asset->equipmentModel->category->nome }} — {{ $asset->equipmentModel->displayName() }}</p>

    <div class="grid">
        <div class="box">
            <div class="row"><span class="label">Status:</span> {{ $asset->statusEnum()->label() }}</div>
            <div class="row"><span class="label">Série:</span> {{ $asset->serie ?? '—' }}</div>
            <div class="row"><span class="label">Localização:</span> {{ $asset->localizacao ?? '—' }}</div>
            <div class="row"><span class="label">Data compra:</span> {{ $asset->data_compra?->format('d/m/Y') ?? '—' }}</div>
            @if($asset->observacoes)
                <div class="row"><span class="label">Observações:</span> {{ $asset->observacoes }}</div>
            @endif
        </div>

        <div class="box" style="text-align:center;">
            @if($hasQr)
                <img src="{{ route('assets.qr-image', $asset) }}" alt="QR Code">
                <p class="muted" style="margin-top:8px;font-size:11px;">Escaneie para abrir a ficha</p>
            @else
                <p class="muted">QR Code indisponível</p>
            @endif
        </div>
    </div>

    <p class="muted" style="margin-top:24px;font-size:11px;">Impresso em {{ now()->format('d/m/Y H:i') }} — Linha Leve</p>
</body>
</html>
