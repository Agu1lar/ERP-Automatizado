<x-flash-message />

@php
    $dayColors = [
        'livre' => 'bg-emerald-100',
        'reservado' => 'bg-blue-200',
        'locado' => 'bg-indigo-500',
        'inspecao' => 'bg-amber-300',
        'manutencao' => 'bg-orange-400',
        'indisponivel' => 'bg-slate-300',
    ];
@endphp

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 inline-flex items-center">
                    Indicadores de frota
                    <x-help-hint text="Ocupação (% de dias comprometidos com locação), rentabilidade por patrimônio (faturamento × manutenção × valor de compra) e calendário mensal de disponibilidade." />
                </h2>
                <p class="text-gray-500 mt-1">Fase 13 — decisões de investimento, compra e desinvestimento na frota.</p>
            </div>

            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-1">
                @foreach(['ocupacao' => 'Ocupação', 'rentabilidade' => 'Rentabilidade', 'investimento' => 'ROI / Payback', 'desinvestimento' => 'Desinvestimento', 'calendario' => 'Calendário'] as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-medium rounded-t-md border-b-2 -mb-px',
                            'border-indigo-600 text-indigo-700 bg-indigo-50' => $tab === $key,
                            'border-transparent text-gray-500 hover:text-gray-800' => $tab !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>

            @if($tab !== 'calendario')
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="grid md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Data inicial</label>
                            <input wire:model.live="date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Data final</label>
                            <input wire:model.live="date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Região da obra</label>
                            <select wire:model.live="region_filter" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Todas</option>
                                @foreach($regionOptions as $region)
                                    <option value="{{ $region->value }}">{{ $region->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($tab === 'ocupacao')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Agrupar por</label>
                                <select wire:model.live="occupancy_group" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="asset">Patrimônio</option>
                                    <option value="model">Modelo</option>
                                    <option value="category">Categoria</option>
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($tab === 'ocupacao')
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4">
                        <p class="text-xs text-indigo-700 uppercase">Taxa média de ocupação</p>
                        <p class="text-3xl font-bold text-indigo-800">{{ number_format($occupancySummary['taxa_ocupacao'], 1, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                        <p class="text-xs text-slate-600 uppercase">Patrimônios na frota</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $occupancySummary['patrimonios'] }}</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                        <p class="text-xs text-blue-700 uppercase">Dias comprometidos</p>
                        <p class="text-2xl font-bold text-blue-800">{{ $occupancySummary['dias_comprometidos'] }}</p>
                        <p class="text-xs text-blue-600 mt-1">Reserva, locação ou inspeção</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                        <p class="text-xs text-gray-600 uppercase">Locações no período</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $occupancySummary['locacoes'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $occupancySummary['dias_periodo'] }} dias analisados</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    @if($occupancy_group === 'category') Categoria
                                    @elseif($occupancy_group === 'model') Modelo
                                    @else Patrimônio @endif
                                </th>
                                @if($occupancy_group !== 'asset')
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Patrimônios</th>
                                @endif
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dias comprometidos</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Locações</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ocupação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($occupancyRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->grupo_nome }}</td>
                                    @if($occupancy_group !== 'asset')
                                        <td class="px-4 py-3 text-right text-gray-600">{{ $row->patrimonios }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $row->dias_comprometidos }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $row->locacoes }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800' => $row->taxa_ocupacao >= 60,
                                            'bg-amber-100 text-amber-800' => $row->taxa_ocupacao >= 30 && $row->taxa_ocupacao < 60,
                                            'bg-red-100 text-red-800' => $row->taxa_ocupacao < 30,
                                        ])>{{ number_format($row->taxa_ocupacao, 1, ',', '.') }}%</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $occupancy_group === 'asset' ? 4 : 5 }}" class="px-4 py-8 text-center text-gray-500">
                                        Nenhum patrimônio ativo no período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'rentabilidade')
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Locações</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Manutenção</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor compra</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Retorno/compra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($profitabilityRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->grupo_nome }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $row->locacoes }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-700">R$ {{ number_format($row->faturamento, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700">R$ {{ number_format($row->custo_manutencao, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">
                                        {{ $row->valor_compra !== null ? 'R$ '.number_format($row->valor_compra, 2, ',', '.') : '—' }}
                                    </td>
                                    <td @class([
                                        'px-4 py-3 text-right font-semibold',
                                        'text-emerald-800' => $row->resultado_operacional >= 0,
                                        'text-red-700' => $row->resultado_operacional < 0,
                                    ])>R$ {{ number_format($row->resultado_operacional, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-indigo-700">
                                        {{ $row->retorno_sobre_compra_percent !== null ? number_format($row->retorno_sobre_compra_percent, 1, ',', '.').'%' : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        Nenhuma locação concluída no período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400">
                    Faturamento: locações concluídas no período. Manutenção: peças + horas × taxa configurável.
                    Retorno/compra = faturamento do período ÷ valor de aquisição cadastrado no patrimônio.
                </p>
            @endif

            @if($tab === 'investimento')
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor compra</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor contábil</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">ROI vida útil</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payback</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ocupação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($investmentRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->grupo_nome }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-700">R$ {{ number_format($row->faturamento, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">
                                        {{ $row->valor_compra !== null ? 'R$ '.number_format($row->valor_compra, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600">
                                        {{ $row->valor_contabil !== null ? 'R$ '.number_format($row->valor_contabil, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-indigo-700">
                                        {{ $row->roi_vida_util_percent !== null ? number_format($row->roi_vida_util_percent, 1, ',', '.').'%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700">
                                        {{ $row->payback_meses !== null ? $row->payback_meses.' meses' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($row->taxa_ocupacao, 1, ',', '.') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">Sem dados de investimento no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400">
                    Valor contábil: depreciação linear ({{ config('fleet.depreciation.useful_life_years') }} anos, residual {{ config('fleet.depreciation.residual_percent') }}%).
                    Payback: valor de compra ÷ resultado mensal médio do período. ROI vida útil: faturamento acumulado histórico vs compra.
                </p>
            @endif

            @if($tab === 'desinvestimento')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Patrimônios sinalizados para avaliar sucata, venda ou substituição com base em ocupação, payback e custo de manutenção.
                </div>
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Manutenção</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ocupação</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($divestmentRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('assets.show', $row->grupo_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $row->grupo_nome }}</a>
                                    </td>
                                    <td @class([
                                        'px-4 py-3 text-right font-medium',
                                        'text-red-700' => $row->resultado_operacional < 0,
                                        'text-emerald-700' => $row->resultado_operacional >= 0,
                                    ])>R$ {{ number_format($row->resultado_operacional, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700">R$ {{ number_format($row->custo_manutencao, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($row->taxa_ocupacao, 1, ',', '.') }}%</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->divestir_motivo }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhum patrimônio sinalizado para desinvestimento no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'calendario')
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="previousCalendarMonth" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">←</button>
                            <h3 class="text-lg font-semibold text-gray-800 min-w-[10rem] text-center capitalize">{{ $calendar['month_label'] }}</h3>
                            <button type="button" wire:click="nextCalendarMonth" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">→</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 flex-1 max-w-xl">
                            <select wire:model.live="calendar_category_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Todas as categorias</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->nome }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="calendar_model_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Todos os modelos</option>
                                @foreach($models as $model)
                                    <option value="{{ $model->id }}">{{ $model->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                        @foreach($calendar['legend'] as $key => $label)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-3 w-3 rounded {{ $dayColors[$key] ?? 'bg-gray-200' }}"></span>
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-max text-xs">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-medium text-gray-500 border-r border-gray-200 min-w-[12rem]">Patrimônio</th>
                                @foreach($calendar['days'] as $day)
                                    <th class="px-1 py-2 text-center font-medium text-gray-500 w-7">{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($calendar['assets'] as $assetRow)
                                <tr class="border-t border-gray-100">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-gray-800 border-r border-gray-100 whitespace-nowrap">
                                        <a href="{{ route('assets.show', $assetRow['id']) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $assetRow['label'] }}</a>
                                    </td>
                                    @foreach($calendar['days'] as $day)
                                        @php $state = $assetRow['days'][(string) $day] ?? 'livre'; @endphp
                                        <td class="p-0.5">
                                            <span
                                                title="{{ $calendar['legend'][$state] ?? $state }}"
                                                class="block h-5 w-5 rounded-sm {{ $dayColors[$state] ?? 'bg-gray-100' }}"
                                            ></span>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($calendar['days']) + 1 }}" class="px-4 py-8 text-center text-gray-500">
                                        Nenhum patrimônio para exibir (limite de 40 por tela).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
