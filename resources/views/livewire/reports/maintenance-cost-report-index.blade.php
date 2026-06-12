<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 inline-flex items-center">
                    Custo de OS vs faturamento
                    <x-help-hint text="Compara faturamento de locações concluídas com o custo total das OS (peças, mão de obra e serviço externo) no mesmo período." />
                </h2>
                <p class="text-gray-500 mt-1">Relatório dedicado para avaliar se a manutenção está consumindo a margem do patrimônio.</p>
            </div>

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
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-4">
                    <p class="text-xs text-emerald-700 uppercase">Faturamento</p>
                    <p class="text-2xl font-bold text-emerald-900">R$ {{ number_format($summary['faturamento'], 2, ',', '.') }}</p>
                    <p class="text-xs text-emerald-700 mt-1">{{ $summary['locacoes'] }} locações</p>
                </div>
                <div class="rounded-lg bg-orange-50 border border-orange-100 p-4">
                    <p class="text-xs text-orange-700 uppercase">Custo total OS</p>
                    <p class="text-2xl font-bold text-orange-900">R$ {{ number_format($summary['custo_os'], 2, ',', '.') }}</p>
                    <p class="text-xs text-orange-700 mt-1">{{ $summary['os_concluidas'] }} OS concluídas</p>
                </div>
                <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4">
                    <p class="text-xs text-indigo-700 uppercase">OS / faturamento</p>
                    <p class="text-2xl font-bold text-indigo-900">
                        {{ $summary['ratio_custo_faturamento_percent'] !== null ? number_format($summary['ratio_custo_faturamento_percent'], 1, ',', '.').'%' : '—' }}
                    </p>
                </div>
                <div @class([
                    'rounded-lg border p-4',
                    'bg-emerald-50 border-emerald-100' => $summary['resultado'] >= 0,
                    'bg-red-50 border-red-100' => $summary['resultado'] < 0,
                ])>
                    <p class="text-xs uppercase {{ $summary['resultado'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">Resultado</p>
                    <p class="text-2xl font-bold {{ $summary['resultado'] >= 0 ? 'text-emerald-900' : 'text-red-900' }}">
                        R$ {{ number_format($summary['resultado'], 2, ',', '.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-1">
                @foreach(['patrimonio' => 'Por patrimônio', 'categoria' => 'Por categoria', 'ordens' => 'Por OS'] as $key => $label)
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

            @if($tab === 'patrimonio')
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Custo OS</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OS/Fat.</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($assetRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('assets.show', $row->grupo_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $row->grupo_nome }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-700">R$ {{ number_format($row->faturamento, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700">R$ {{ number_format($row->custo_os, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $row->ratio_percent !== null ? number_format($row->ratio_percent, 1, ',', '.').'%' : '—' }}</td>
                                    <td @class([
                                        'px-4 py-3 text-right font-medium',
                                        'text-emerald-800' => $row->resultado >= 0,
                                        'text-red-700' => $row->resultado < 0,
                                    ])>R$ {{ number_format($row->resultado, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $row->os_concluidas }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Sem dados no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'categoria')
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Custo OS</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Peças</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mão de obra</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Externo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OS/Fat.</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Loc.</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($categoryRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('fleet.categories.show', $row->grupo_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $row->grupo_nome }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-700">R$ {{ number_format($row->faturamento, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700 font-medium">R$ {{ number_format($row->custo_os, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">R$ {{ number_format($row->custo_pecas, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">R$ {{ number_format($row->custo_mao_obra, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">R$ {{ number_format($row->custo_externo, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $row->ratio_percent !== null ? number_format($row->ratio_percent, 1, ',', '.').'%' : '—' }}</td>
                                    <td @class([
                                        'px-4 py-3 text-right font-medium',
                                        'text-emerald-800' => $row->resultado >= 0,
                                        'text-red-700' => $row->resultado < 0,
                                    ])>R$ {{ number_format($row->resultado, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $row->locacoes }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $row->os_concluidas }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">Sem dados no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'ordens')
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">OS</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conclusão</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Custo OS</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fat. patrimônio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($orderRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('maintenance.show', $row->order) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $row->codigo }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800">{{ $row->asset_label }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row->tipo }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row->completed_at?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700">R$ {{ number_format($row->custo_total, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">R$ {{ number_format($row->faturamento_patrimonio_periodo, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhuma OS concluída no período.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="text-xs text-gray-400">
                Peças e horas das OS concluídas no período; serviço externo quando informado na OS.
                Faturamento: locações concluídas no período (filtro de região quando aplicável).
            </p>
        </div>
    </div>
</div>
