<x-flash-message />



<div>

    <div class="py-8">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div>

                <h2 class="text-2xl font-bold text-gray-800 inline-flex items-center">

                    Análise financeira

                    <x-help-hint text="Compara faturamento de locações concluídas com custo de manutenção (peças + mão de obra estimada por hora) das OS concluídas no período." />

                </h2>

                <p class="text-gray-500 mt-1">Faturamento − custo de manutenção (peças e mão de obra), no geral, por categoria ou por patrimônio.</p>

            </div>



            <div class="bg-white rounded-lg shadow p-6 space-y-4">

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

                        <label class="block text-sm font-medium text-gray-700">Visualização</label>

                        <select wire:model.live="view_mode" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">

                            <option value="geral">Geral (totais)</option>

                            <option value="category">Por categoria</option>

                            <option value="asset">Por patrimônio</option>

                        </select>

                    </div>

                </div>



                <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 pt-2 border-t border-gray-100">

                    <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-4">

                        <p class="text-xs text-emerald-700 uppercase">Faturamento</p>

                        <p class="text-xl font-bold text-emerald-800">R$ {{ number_format($summary['faturamento'], 2, ',', '.') }}</p>

                        <p class="text-xs text-emerald-600 mt-1">{{ $summary['locacoes'] }} locação(ões)</p>

                    </div>

                    <div class="rounded-lg bg-orange-50 border border-orange-100 p-4">

                        <p class="text-xs text-orange-700 uppercase">Custo peças</p>

                        <p class="text-xl font-bold text-orange-800">R$ {{ number_format($summary['custo_pecas'], 2, ',', '.') }}</p>

                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-100 p-4">

                        <p class="text-xs text-amber-700 uppercase">Custo mão de obra</p>

                        <p class="text-xl font-bold text-amber-800">R$ {{ number_format($summary['custo_mao_obra'], 2, ',', '.') }}</p>

                        <p class="text-xs text-amber-600 mt-1">R$ {{ number_format($summary['taxa_hora_mao_obra'], 2, ',', '.') }}/h</p>

                    </div>

                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">

                        <p class="text-xs text-slate-600 uppercase">Custo total manutenção</p>

                        <p class="text-xl font-bold text-slate-800">R$ {{ number_format($summary['custo_manutencao'], 2, ',', '.') }}</p>

                        <p class="text-xs text-slate-500 mt-1">{{ $summary['os_concluidas'] }} OS concluída(s)</p>

                    </div>

                    <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4">

                        <p class="text-xs text-indigo-700 uppercase">Resultado</p>

                        <p @class([

                            'text-xl font-bold',

                            'text-emerald-800' => $summary['resultado'] >= 0,

                            'text-red-700' => $summary['resultado'] < 0,

                        ])>R$ {{ number_format($summary['resultado'], 2, ',', '.') }}</p>

                        @if($summary['margem_percent'] !== null)

                            <p class="text-xs text-indigo-600 mt-1">Margem {{ number_format($summary['margem_percent'], 1, ',', '.') }}%</p>

                        @endif

                    </div>

                </div>

            </div>



            <div class="bg-white rounded-lg shadow overflow-hidden">

                @if($view_mode === 'geral')

                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">

                        <h3 class="text-sm font-semibold text-gray-700">Resumo consolidado</h3>

                    </div>

                @endif

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">

                                @if($view_mode === 'category')

                                    Categoria

                                @elseif($view_mode === 'asset')

                                    Patrimônio

                                @else

                                    Visão

                                @endif

                            </th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Locações</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OS</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Peças</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Mão de obra</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Resultado</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        @forelse($rows as $row)

                            <tr class="text-sm">

                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row->grupo_nome }}</td>

                                <td class="px-4 py-3 text-right text-gray-600">{{ $row->locacoes }}</td>

                                <td class="px-4 py-3 text-right text-gray-600">{{ $row->os_concluidas }}</td>

                                <td class="px-4 py-3 text-right text-emerald-700">R$ {{ number_format($row->faturamento, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right text-orange-700">R$ {{ number_format($row->custo_pecas ?? 0, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right text-amber-700">R$ {{ number_format($row->custo_mao_obra ?? 0, 2, ',', '.') }}</td>

                                <td @class([

                                    'px-4 py-3 text-right font-semibold',

                                    'text-emerald-800' => $row->resultado >= 0,

                                    'text-red-700' => $row->resultado < 0,

                                ])>R$ {{ number_format($row->resultado, 2, ',', '.') }}</td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">

                                    Nenhum dado de faturamento ou manutenção no período.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>



            <p class="text-xs text-gray-400">

                Faturamento: locações concluídas no período. Manutenção: peças das OS concluídas + horas registradas × taxa horária (configurável em MAINTENANCE_HOURLY_RATE).

                Não inclui valor de aquisição do patrimônio. Período padrão: últimos 90 dias.

            </p>

        </div>

    </div>

</div>


