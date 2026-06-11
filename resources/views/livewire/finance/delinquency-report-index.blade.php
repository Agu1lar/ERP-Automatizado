<x-flash-message />



<div>

    <div class="py-8">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap justify-between items-center gap-4">

                <div>

                    <h2 class="text-xl font-semibold text-gray-800">Inadimplência</h2>

                    <p class="text-sm text-gray-500 mt-0.5">Aging 30 / 60 / 90+ dias por cliente com multa e juros</p>

                </div>

                <div class="flex flex-wrap gap-2">

                    @if($canManageCharges)

                        <button type="button" wire:click="openChargeModal" class="btn-primary text-sm inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            Configurar multa e juros
                            <x-help-hint text="Defina regras de multa (%) e juros (% ao mês) por escopo global, cliente ou contrato. Salve a regra e aplique em lote por período de vencimento." class="ml-2" />
                        </button>

                    @endif

                    <a href="{{ route('finance.receivables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Títulos</a>

                    <a href="{{ route('finance.export', ['tipo' => 'inadimplencia']) }}" class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Exportar CSV</a>

                </div>

            </div>



            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="bg-white rounded-lg shadow p-4">

                    <p class="text-xs text-gray-500 uppercase">Total em aberto</p>

                    <p class="text-2xl font-bold text-gray-900">R$ {{ number_format($summary['total_aberto'], 2, ',', '.') }}</p>

                </div>

                <div class="bg-red-50 rounded-lg border border-red-200 p-4">

                    <p class="text-xs text-red-700 uppercase">Total atrasado (valor limpo)</p>

                    <p class="text-2xl font-bold text-red-800">R$ {{ number_format($summary['total_atrasado'], 2, ',', '.') }}</p>

                    <p class="text-xs text-red-600 mt-1">{{ $summary['clientes'] }} cliente(s)</p>

                </div>

                <div class="bg-amber-50 rounded-lg border border-amber-200 p-4 sm:col-span-2">

                    <p class="text-xs text-amber-800 uppercase mb-2">Total com encargos (multa + juros)</p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">

                        <div>

                            <span class="text-gray-500 block text-xs">Valor limpo</span>

                            <strong>R$ {{ number_format($chargeSummary['valor_limpo'], 2, ',', '.') }}</strong>

                        </div>

                        <div>

                            <span class="text-gray-500 block text-xs">Multa</span>

                            <strong class="text-amber-900">R$ {{ number_format($chargeSummary['multa_valor'], 2, ',', '.') }}</strong>

                        </div>

                        <div>

                            <span class="text-gray-500 block text-xs">Juros</span>

                            <strong class="text-amber-900">R$ {{ number_format($chargeSummary['juros_valor'], 2, ',', '.') }}</strong>

                        </div>

                        <div>

                            <span class="text-gray-500 block text-xs">Total</span>

                            <strong class="text-lg text-amber-950">R$ {{ number_format($chargeSummary['valor_total'], 2, ',', '.') }}</strong>

                        </div>

                    </div>

                </div>

            </div>



            <div class="bg-white rounded-lg shadow p-4">

                <p class="text-xs text-gray-500 uppercase mb-2">Faixas de atraso (valor limpo)</p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">

                    <div>1–30 dias: <strong>R$ {{ number_format($summary['ate_30'], 2, ',', '.') }}</strong></div>

                    <div>31–60 dias: <strong>R$ {{ number_format($summary['ate_60'], 2, ',', '.') }}</strong></div>

                    <div>61–90 dias: <strong>R$ {{ number_format($summary['ate_90'], 2, ',', '.') }}</strong></div>

                    <div>90+ dias: <strong class="text-red-700">R$ {{ number_format($summary['acima_90'], 2, ',', '.') }}</strong></div>

                </div>

            </div>



            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Filtrar cliente..." class="rounded-md border-gray-300 shadow-sm max-w-md text-sm" />



            <div class="bg-white rounded-lg shadow overflow-hidden">

                <div class="px-4 py-3 border-b border-gray-200">

                    <h3 class="text-sm font-semibold text-gray-800">Resumo por cliente</h3>

                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Atrasado</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">1–30</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">31–60</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">61–90</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">90+</th>

                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Títulos</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        @forelse($customers as $row)

                            <tr class="hover:bg-gray-50">

                                <td class="px-4 py-3 font-medium">

                                    <a href="{{ route('customers.show', $row->customer_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $row->customer_nome }}</a>

                                </td>

                                <td class="px-4 py-3 text-right font-semibold text-red-700">R$ {{ number_format($row->total_atrasado, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right">R$ {{ number_format($row->ate_30, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right">R$ {{ number_format($row->ate_60, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right">R$ {{ number_format($row->ate_90, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right text-red-800">R$ {{ number_format($row->acima_90, 2, ',', '.') }}</td>

                                <td class="px-4 py-3 text-right text-gray-500">{{ $row->titulos_atrasados }}</td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum cliente inadimplente no momento.</td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>



            <div class="bg-white rounded-lg shadow overflow-hidden">

                <div class="px-4 py-3 border-b border-gray-200">

                    <h3 class="text-sm font-semibold text-gray-800">Detalhamento por título (valor limpo + encargos)</h3>

                    <p class="text-xs text-gray-500 mt-0.5">Multa em % sobre o principal; juros proporcionais aos dias de atraso (% ao mês ÷ 30 × dias)</p>

                </div>

                <div class="overflow-x-auto">

                    <table class="min-w-full divide-y divide-gray-200 text-sm">

                        <thead class="bg-gray-50">

                            <tr>

                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>

                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>

                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locação</th>

                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dias</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor limpo</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Multa %</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Multa R$</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Juros % a.m.</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Juros R$</th>

                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>

                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regra</th>

                            </tr>

                        </thead>

                        <tbody class="divide-y divide-gray-200">

                            @forelse($titleDetails as $row)

                                <tr class="hover:bg-gray-50 {{ $row->is_applied ? 'bg-emerald-50/40' : '' }}">

                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->title->codigo }}</td>

                                    <td class="px-4 py-3">{{ $row->title->customer->nome }}</td>

                                    <td class="px-4 py-3 text-gray-600">{{ $row->title->rental?->codigo ?? '—' }}</td>

                                    <td class="px-4 py-3">{{ $row->title->vencimento->format('d/m/Y') }}</td>

                                    <td class="px-4 py-3 text-right text-red-700">{{ $row->dias_atraso }}</td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($row->valor_limpo, 2, ',', '.') }}</td>

                                    <td class="px-4 py-3 text-right">{{ number_format($row->multa_percent, 2, ',', '.') }}%</td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($row->multa_valor, 2, ',', '.') }}</td>

                                    <td class="px-4 py-3 text-right">{{ number_format($row->juros_mensal_percent, 2, ',', '.') }}%</td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($row->juros_valor, 2, ',', '.') }}</td>

                                    <td class="px-4 py-3 text-right font-semibold">R$ {{ number_format($row->valor_total, 2, ',', '.') }}</td>

                                    <td class="px-4 py-3 text-xs text-gray-500">

                                        {{ $row->rule_source }}

                                        @if($row->is_applied)

                                            <span class="block text-emerald-700">Aplicado</span>

                                        @endif

                                    </td>

                                </tr>

                            @empty

                                <tr>

                                    <td colspan="12" class="px-4 py-8 text-center text-gray-500">Nenhum título em atraso.</td>

                                </tr>

                            @endforelse

                        </tbody>

                        @if($titleDetails->isNotEmpty())

                            <tfoot class="bg-gray-50 font-semibold">

                                <tr>

                                    <td colspan="5" class="px-4 py-3 text-right text-xs uppercase text-gray-500">Totais</td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($chargeSummary['valor_limpo'], 2, ',', '.') }}</td>

                                    <td class="px-4 py-3"></td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($chargeSummary['multa_valor'], 2, ',', '.') }}</td>

                                    <td class="px-4 py-3"></td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($chargeSummary['juros_valor'], 2, ',', '.') }}</td>

                                    <td class="px-4 py-3 text-right">R$ {{ number_format($chargeSummary['valor_total'], 2, ',', '.') }}</td>

                                    <td class="px-4 py-3"></td>

                                </tr>

                            </tfoot>

                        @endif

                    </table>

                </div>

            </div>

        </div>

    </div>



    @include('livewire.finance.partials.late-fee-config-modal')

</div>

