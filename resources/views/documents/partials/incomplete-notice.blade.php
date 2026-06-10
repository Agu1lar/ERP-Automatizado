@if(!empty($fichaWarnings) && count($fichaWarnings) > 0)
    <div style="border: 1px solid #f59e0b; background: #fffbeb; padding: 8px 10px; margin-bottom: 14px; font-size: 9px; color: #92400e;">
        <strong>! Ficha incompleta</strong> —
        {{ collect($fichaWarnings)->pluck('message')->implode(' · ') }}
    </div>
@endif
