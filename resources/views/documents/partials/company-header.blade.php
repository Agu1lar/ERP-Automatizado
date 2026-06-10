<div class="header">
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo"><br>
                @endif
                <p class="company-name">{{ $company['name'] ?? 'ACESSO equipamentos' }}</p>
                <div class="company-meta">
                    @if(!empty($company['document'])){{ $company['document'] }}<br>@endif
                    @if(!empty($company['address'])){{ $company['address'] }}<br>@endif
                    @if(!empty($company['phone']))Tel: {{ $company['phone'] }}@endif
                    @if(!empty($company['email'])) — {{ $company['email'] }}@endif
                </div>
            </td>
            <td style="width: 45%;">
                <div class="doc-title">{{ $documentTitle }}</div>
                @if(!empty($documentCode))
                    <div class="doc-code">{{ $documentCode }}</div>
                @endif
                @if(!empty($documentBadge))
                    <div style="text-align: right; margin-top: 6px;">
                        <span class="badge">{{ $documentBadge }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>
</div>
