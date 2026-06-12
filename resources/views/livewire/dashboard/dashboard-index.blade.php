<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
            <h2 class="text-xl font-semibold text-gray-800">
                Dashboard — {{ $activeCompany?->nome ?? config('app.name') }}
            </h2>
            @if($activeCompany?->formattedCnpj())
                <p class="text-sm text-gray-500 mt-0.5">CNPJ {{ $activeCompany->formattedCnpj() }}</p>
            @endif
        </div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if($overdueReturnsCount > 0)
                <div class="rounded-lg border border-red-300 bg-red-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-red-800">Retornos atrasados</p>
                            <p class="text-sm text-red-700 mt-1">{{ $overdueReturnsCount }} locação(ões) com previsão de retorno vencida.</p>
                        </div>
                        <a href="{{ route('rentals.index', ['aba' => 'painel', 'atrasados' => 1]) }}" wire:navigate class="text-sm font-medium text-red-800 hover:underline">Ver no painel →</a>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach($overdueReturns as $rental)
                            <li>
                                <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="text-red-800 hover:underline font-medium">{{ $rental->codigo }}</a>
                                <span class="text-red-600">— {{ $rental->customer->nome }} · {{ $rental->daysOverdue() }} {{ $rental->daysOverdue() === 1 ? 'dia' : 'dias' }} atrasado</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($receivableWeek && $receivableWeek['quantidade'] > 0)
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-emerald-900">A receber esta semana</p>
                            <p class="text-sm text-emerald-800 mt-1">
                                R$ {{ number_format($receivableWeek['total'], 2, ',', '.') }} —
                                {{ $receivableWeek['quantidade'] }} título(s) com vencimento até {{ \Carbon\Carbon::parse($receivableWeek['fim'])->format('d/m') }}
                            </p>
                        </div>
                        <a href="{{ route('finance.receivables') }}" wire:navigate class="text-sm font-medium text-emerald-900 hover:underline">Ver títulos →</a>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach($receivableWeekTitles as $title)
                            <li>
                                <a href="{{ route('finance.receivables', ['q' => $title->codigo]) }}" wire:navigate class="text-emerald-900 hover:underline font-medium">{{ $title->codigo }}</a>
                                <span class="text-emerald-700">
                                    — {{ $title->customer->nome }}
                                    · vence {{ $title->vencimento->format('d/m') }}
                                    · R$ {{ number_format($title->valor, 2, ',', '.') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($billingCycleDueCount > 0)
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-amber-900">Ciclos de faturamento vencidos</p>
                            <p class="text-sm text-amber-800 mt-1">
                                {{ $billingCycleDueCount }} locação(ões) com renovação de faturamento pendente
                                @if($pendingRenewalQueueCount > 0)
                                    · {{ $pendingRenewalQueueCount }} já na fila a faturar
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('finance.billing-queue') }}" wire:navigate class="text-sm font-medium text-amber-900 hover:underline">Fila a faturar →</a>
                    </div>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach($billingCycleDueRentals as $rental)
                            <li>
                                <a href="{{ route('rentals.show', $rental) }}#faturamento" wire:navigate class="text-amber-900 hover:underline font-medium">{{ $rental->codigo }}</a>
                                <span class="text-amber-700">
                                    — {{ $rental->customer->nome }}
                                    · ciclo venceu {{ $rental->next_billing_at?->format('d/m/Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($financeSummary && $financeSummary['total_atrasado'] > 0)
                <div class="rounded-lg border border-red-300 bg-red-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold text-red-800">Inadimplência financeira</p>
                            <p class="text-sm text-red-700 mt-1">
                                R$ {{ number_format($financeSummary['total_atrasado'], 2, ',', '.') }} em atraso —
                                {{ $financeSummary['clientes'] }} cliente(s)
                            </p>
                        </div>
                        <a href="{{ route('finance.delinquency') }}" wire:navigate class="text-sm font-medium text-red-800 hover:underline">Ver relatório →</a>
                    </div>
                </div>
            @endif

            @if($showAnalytics && ($preventiveDueCount > 0 || $preventiveUpcomingCount > 0 || $incompleteFichasCount > 0))
                <div class="grid md:grid-cols-2 gap-4">
                    @if($preventiveDueCount > 0)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <p class="font-semibold text-red-800">Manutenção preventiva vencida</p>
                            <p class="text-sm text-red-700 mt-1">{{ $preventiveDueCount }} patrimônio(s) ultrapassaram o intervalo de horas configurado.</p>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach(array_slice($preventiveDue, 0, 5) as $item)
                                    <li>
                                        <a href="{{ route('assets.show', $item['asset']) }}" wire:navigate class="text-red-800 hover:underline font-medium">{{ $item['asset']->codigo_patrimonio }}</a>
                                        <span class="text-red-600">— {{ $item['rule']->descricao }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('maintenance.preventive.index') }}" wire:navigate class="inline-block mt-2 text-sm text-red-800 hover:underline">Regras preventivas →</a>
                        </div>
                    @endif
                    @if($preventiveUpcomingCount > 0)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-900">Preventiva próxima do vencimento</p>
                            <p class="text-sm text-amber-800 mt-1">{{ $preventiveUpcomingCount }} patrimônio(s) entram na janela de alerta antecipado.</p>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach(array_slice($preventiveUpcoming, 0, 5) as $item)
                                    <li>
                                        <a href="{{ route('assets.show', $item['asset']) }}" wire:navigate class="text-amber-900 hover:underline font-medium">{{ $item['asset']->codigo_patrimonio }}</a>
                                        <span class="text-amber-700">— faltam {{ number_format($item['proxima_em'] ?? 0, 0, ',', '.') }} h</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($incompleteFichasCount > 0)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-800">Fichas incompletas</p>
                            <p class="text-sm text-amber-700 mt-1">{{ $incompleteFichasCount }} patrimônio(s) com campos obrigatórios da ficha pendentes.</p>
                            <a href="{{ route('assets.index') }}" wire:navigate class="inline-block mt-2 text-sm text-amber-800 hover:underline">Ver patrimônios</a>
                        </div>
                    @endif
                </div>
            @endif

            @if($showAnalytics)
                <div class="flex justify-end">
                    <a href="{{ route('reports.commercial') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Relatório comercial por tipo de equipamento →</a>
                </div>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Frota por status</h3>
                        <div class="space-y-2">
                            @foreach($statusLabels as $value => $label)
                                @php $count = $statusCounts[$value] ?? 0; @endphp
                                <div>
                                    <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                        <span>{{ $label }}</span>
                                        <span>{{ $count }}</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500 rounded-full" style="width: {{ round(($count / $fleetTotal) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Locações por status</h3>
                        <div class="space-y-2">
                            @foreach($rentalLabels as $value => $label)
                                @php $count = $rentalCounts[$value] ?? 0; @endphp
                                <div>
                                    <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                        <span>{{ $label }}</span>
                                        <span>{{ $count }}</span>
                                    </div>
                                    @php $rentalTotal = max(1, $rentalCounts->sum()); @endphp
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ round(($count / $rentalTotal) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">OS por status</h3>
                        <div class="space-y-2">
                            @foreach($maintenanceLabels as $value => $label)
                                @php $count = $maintenanceCounts[$value] ?? 0; @endphp
                                <div>
                                    <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                                        <span>{{ $label }}</span>
                                        <span>{{ $count }}</span>
                                    </div>
                                    @php $osTotal = max(1, $maintenanceCounts->sum()); @endphp
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 rounded-full" style="width: {{ round(($count / $osTotal) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Patrimônios bloqueados</h3>
                    @forelse($blockedAssets as $asset)
                        <div class="py-2 border-b border-gray-100 last:border-0">
                            <a href="{{ route('assets.show', $asset) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">
                                {{ $asset->codigo_patrimonio }}
                            </a>
                            <p class="text-sm text-gray-500">{{ $asset->equipmentDisplayName() }}</p>
                            @if($asset->motivo_bloqueio)
                                <p class="text-xs text-red-600 mt-1">{{ $asset->motivo_bloqueio }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Nenhum patrimônio bloqueado.</p>
                    @endforelse
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Últimas alterações de status</h3>
                    @forelse($recentChanges as $change)
                        <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                            <span class="font-medium">{{ $change->asset?->codigo_patrimonio ?? '—' }}</span>
                            <span class="text-gray-500">
                                {{ $change->status_anterior ? \App\Enums\AssetStatus::from($change->status_anterior)->label() : '—' }}
                                →
                                {{ \App\Enums\AssetStatus::from($change->status_novo)->label() }}
                            </span>
                            <p class="text-xs text-gray-400">{{ $change->created_at->format('d/m/Y H:i') }} — {{ $change->user?->name ?? 'Sistema' }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Nenhuma alteração registrada.</p>
                    @endforelse
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Saídas pendentes</h3>
                    @forelse($pendingCheckouts as $rental)
                        <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                            <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $rental->codigo }}</a>
                            <p class="text-gray-500">{{ $rental->asset->codigo_patrimonio }} → {{ $rental->customer->nome }}</p>
                            <p class="text-xs text-gray-400">Reservado em {{ $rental->reserved_at->format('d/m/Y H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Nenhuma saída pendente.</p>
                    @endforelse
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Retornos previstos para hoje</h3>
                    @forelse($dueReturns as $rental)
                        <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                            <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $rental->codigo }}</a>
                            <p class="text-gray-500">{{ $rental->asset->codigo_patrimonio }} — {{ $rental->customer->nome }}</p>
                            <p class="text-xs text-amber-600">Previsão: {{ $rental->expected_return_at?->format('d/m/Y') }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Nenhum retorno previsto para hoje.</p>
                    @endforelse
                    @if($overdueReturnsCount > 0)
                        <a href="{{ route('rentals.index', ['aba' => 'painel', 'atrasados' => 1]) }}" wire:navigate class="inline-block mt-3 text-xs text-red-600 hover:underline">{{ $overdueReturnsCount }} retorno(s) atrasado(s)</a>
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">OS atrasadas</h3>
                    @forelse($overdueOrders as $order)
                        <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                            <a href="{{ route('maintenance.show', $order) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $order->codigo }}</a>
                            <p class="text-gray-500">{{ $order->asset->codigo_patrimonio }}</p>
                            <p class="text-xs text-red-600">Previsão: {{ $order->expected_completion_at?->format('d/m/Y') }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">Nenhuma OS atrasada.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
