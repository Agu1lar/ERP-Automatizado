<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha {{ $asset->codigo_patrimonio }}</title>
    @include('documents.partials.styles')
</head>
<body>
<div class="page">
    @include('documents.partials.company-header', [
        'documentTitle' => 'FICHA DO PATRIMÔNIO',
        'documentCode' => $asset->codigo_patrimonio,
    ])

    @include('documents.partials.incomplete-notice')

    <table class="header-table" style="margin-bottom: 16px;">
        <tr>
            <td style="width: 65%; vertical-align: top;">
                <div class="section">
                    <div class="section-title">Equipamento</div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">Categoria</td>
                            <td class="value">{{ $asset->equipmentModel->category->nome }}</td>
                        </tr>
                        <tr>
                            <td class="label">Modelo</td>
                            <td class="value">{{ $asset->equipmentModel->displayName() }}</td>
                        </tr>
                        <tr>
                            <td class="label">Status</td>
                            <td class="value">{{ $asset->statusEnum()->label() }}</td>
                        </tr>
                        <tr>
                            <td class="label">Série</td>
                            <td class="value">{{ $asset->serie ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Horímetro</td>
                            <td class="value">{{ $asset->horimetro !== null ? number_format($asset->horimetro, 2, ',', '.').' h' : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Localização</td>
                            <td class="value">{{ $asset->localizacao ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Data aquisição</td>
                            <td class="value">{{ $asset->data_compra?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
                @if($asset->descricao)
                <div class="section">
                    <div class="section-title">Descrição</div>
                    <div class="text-block">{{ $asset->descricao }}</div>
                </div>
                @endif
                @if($asset->observacoes)
                <div class="section">
                    <div class="section-title">Observações operacionais</div>
                    <div class="text-block">{{ $asset->observacoes }}</div>
                </div>
                @endif
            </td>
            <td style="width: 35%; text-align: center; vertical-align: top;">
                <div style="border: 1px solid #d1d5db; padding: 12px; text-align: center;">
                    @if($qrBase64)
                        <img src="{{ $qrBase64 }}" alt="QR Code" style="width: 120px; height: 120px;">
                        <p style="font-size: 9px; color: #666; margin-top: 6px;">Escaneie para abrir a ficha</p>
                    @else
                        <p style="font-size: 10px; color: #888;">QR Code indisponível</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @include('documents.partials.custom-fields')

    <div class="footer">
        Documento gerado em {{ $generatedAt->format('d/m/Y H:i') }} — {{ \App\Support\BrandContext::documentFooter($company) }}
    </div>
</div>
</body>
</html>
