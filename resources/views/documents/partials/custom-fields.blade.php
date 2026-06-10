@if(!empty($customFieldRows))
    <div class="section">
        <div class="section-title">Campos personalizados</div>
        <table class="info-grid">
            @foreach($customFieldRows as $row)
                <tr>
                    <td class="label">{{ $row['label'] }}</td>
                    <td class="value" colspan="3">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
