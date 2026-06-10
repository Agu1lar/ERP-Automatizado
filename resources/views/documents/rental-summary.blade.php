<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $rental->codigo }} — Locação</title>
    @include('documents.partials.styles')
</head>
<body>
<div class="page">
    @include('documents.partials.company-header', [
        'documentTitle' => 'RESUMO DE LOCAÇÃO',
        'documentCode' => $rental->codigo,
        'documentBadge' => $rental->statusEnum()->label(),
    ])

    @include('documents.partials.incomplete-notice')

    <div class="section">
        <div class="section-title">Dados gerais</div>
        <table class="info-grid">
            <tr>
                <td class="label">Cliente</td>
                <td class="value">{{ $rental->customer->nome }}</td>
                <td class="label">Documento</td>
                <td class="value">{{ $rental->customer->formattedDocument() }}</td>
            </tr>
            <tr>
                <td class="label">Patrimônio</td>
                <td class="value">{{ $rental->asset->codigo_patrimonio }}</td>
                <td class="label">Equipamento</td>
                <td class="value">{{ $rental->asset->equipmentModel->displayName() }}</td>
            </tr>
            <tr>
                <td class="label">Reservado em</td>
                <td class="value">{{ $rental->reserved_at->format('d/m/Y H:i') }}</td>
                <td class="label">Previsão retorno</td>
                <td class="value">{{ $rental->expected_return_at?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @if($rental->local_obra)
            <tr>
                <td class="label">Local da obra</td>
                <td class="value" colspan="3">{{ $rental->local_obra }}</td>
            </tr>
            @endif
            @if($rental->checkout_at)
            <tr>
                <td class="label">Saída</td>
                <td class="value">{{ $rental->checkout_at->format('d/m/Y H:i') }}</td>
                <td class="label">Por</td>
                <td class="value">{{ $rental->checkoutByUser?->name ?? '—' }}</td>
            </tr>
            @endif
            @if($rental->returned_at)
            <tr>
                <td class="label">Retorno</td>
                <td class="value">{{ $rental->returned_at->format('d/m/Y H:i') }}</td>
                <td class="label">Por</td>
                <td class="value">{{ $rental->returnedByUser?->name ?? '—' }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Cliente</div>
        <table class="info-grid">
            <tr>
                <td class="label">Contato</td>
                <td class="value">{{ $rental->customer->contato ?? '—' }}</td>
                <td class="label">Telefone</td>
                <td class="value">{{ $rental->customer->telefone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">E-mail</td>
                <td class="value">{{ $rental->customer->email ?? '—' }}</td>
                <td class="label">Endereço</td>
                <td class="value">{{ $rental->customer->endereco ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Equipamento</div>
        <table class="info-grid">
            <tr>
                <td class="label">Modelo</td>
                <td class="value">{{ $rental->asset->equipmentModel->displayName() }}</td>
                <td class="label">Série</td>
                <td class="value">{{ $rental->asset->serie ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Horímetro atual</td>
                <td class="value">{{ $rental->asset->horimetro !== null ? number_format($rental->asset->horimetro, 2, ',', '.').' h' : '—' }}</td>
                <td class="label">Horímetro saída</td>
                <td class="value">{{ $rental->horimetro_saida !== null ? number_format($rental->horimetro_saida, 2, ',', '.').' h' : '—' }}</td>
            </tr>
            <tr>
                <td class="label">Horímetro retorno</td>
                <td class="value" colspan="3">{{ $rental->horimetro_retorno !== null ? number_format($rental->horimetro_retorno, 2, ',', '.').' h' : '—' }}</td>
            </tr>
        </table>
        @if($rental->asset->descricao)
            <div class="text-block" style="margin-top: 8px;">{{ $rental->asset->descricao }}</div>
        @endif
        @if($rental->ficha_descricao)
            <div class="text-block" style="margin-top: 8px;">{{ $rental->ficha_descricao }}</div>
        @endif
    </div>

    @include('documents.partials.custom-fields')

    @if($rental->observacoes)
    <div class="section">
        <div class="section-title">Observações</div>
        <div class="text-block">{{ $rental->observacoes }}</div>
    </div>
    @endif

    @foreach($rental->checklists as $checklist)
    <div class="section">
        <div class="section-title">Checklist de {{ $checklist->tipoEnum()->label() }} — {{ $checklist->completed_at->format('d/m/Y H:i') }}</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 8%;">OK</th>
                    <th>Item</th>
                </tr>
            </thead>
            <tbody>
                @foreach($checklist->items as $item)
                    <tr>
                        <td class="num">{{ $item->checked ? '✓' : '✗' }}</td>
                        <td>{{ $item->item_label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($checklist->observacoes)
            <p style="font-size: 10px; margin-top: 6px;"><strong>Obs.:</strong> {{ $checklist->observacoes }}</p>
        @endif
        <p style="font-size: 9px; color: #888;">Registrado por: {{ $checklist->user?->name ?? 'Sistema' }}</p>
    </div>
    @endforeach

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Cliente</div></td>
            <td><div class="sign-line">Responsável Linha Leve</div></td>
        </tr>
    </table>

    <div class="footer">
        Documento gerado em {{ $generatedAt->format('d/m/Y H:i') }} — {{ $company['name'] ?? 'Linha Leve' }}
    </div>
</div>
</body>
</html>
