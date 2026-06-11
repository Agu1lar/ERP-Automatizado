<x-mail::message>
# Alertas operacionais

Olá, {{ $user->name }}.

Resumo do dia **{{ now()->format('d/m/Y') }}** — itens que exigem atenção no pátio, comercial ou oficina.

@if(isset($sections['overdue_returns']))
## Retornos atrasados ({{ count($sections['overdue_returns']) }})

<x-mail::table>
| Locação | Cliente | Patrimônio | Atraso |
|:--------|:--------|:-----------|:-------|
@foreach($sections['overdue_returns'] as $rental)
| {{ $rental->codigo }} | {{ $rental->customer?->nome ?? '—' }} | {{ $rental->asset?->codigo_patrimonio ?? '—' }} | {{ $rental->daysOverdue() }} {{ $rental->daysOverdue() === 1 ? 'dia' : 'dias' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('rentals.index', ['aba' => 'painel', 'atrasados' => 1])">
Ver painel de locados
</x-mail::button>
@endif

@if(isset($sections['overdue_orders']))
## OS atrasadas ({{ count($sections['overdue_orders']) }})

<x-mail::table>
| OS | Patrimônio | Previsão | Responsável |
|:---|:-----------|:---------|:------------|
@foreach($sections['overdue_orders'] as $order)
| {{ $order->codigo }} | {{ $order->asset?->codigo_patrimonio ?? '—' }} | {{ $order->expected_completion_at?->format('d/m/Y') ?? '—' }} | {{ $order->assignedToUser?->name ?? '—' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('maintenance.index')">
Ver manutenção
</x-mail::button>
@endif

@if(isset($sections['preventive_due']))
## Preventiva vencida por horímetro ({{ count($sections['preventive_due']) }})

<x-mail::table>
| Patrimônio | Regra | Horas desde última |
|:-----------|:------|:-------------------|
@foreach($sections['preventive_due'] as $item)
| {{ $item['asset']->codigo_patrimonio }} | {{ $item['rule']->descricao }} | {{ $item['horas_desde_ultima'] !== null ? number_format($item['horas_desde_ultima'], 1, ',', '.') : '—' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('dashboard')">
Abrir dashboard
</x-mail::button>
@endif

<x-mail::subcopy>
Você recebe este e-mail porque possui permissão operacional no {{ config('app.name') }}.
</x-mail::subcopy>
</x-mail::message>
