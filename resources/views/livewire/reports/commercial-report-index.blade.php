<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Relatório comercial</h2>
                <p class="text-gray-500 mt-1">Faturamento por tipo de equipamento ou por responsável comercial da locação.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="grid md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data inicial</label>
                        <input wire:model.live="date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data final</label>
                        <input wire:model.live="date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Agrupar por</label>
                        <select wire:model.live="group_by" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="model">Modelo (ex.: Martelete Bosch, Betoneira 400L)</option>
                            <option value="category">Categoria (ex.: Marteletes, Betoneiras)</option>
                            <option value="user">Usuário responsável (vendedor)</option>
                        </select>
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
                    <div class="md:text-right space-y-2">
                        <p class="text-sm text-gray-500">Total no período</p>
                        <p class="text-2xl font-bold text-emerald-700">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
                        <a
                            href="{{ route('reports.commercial.export', array_filter(['date_from' => $date_from, 'date_to' => $date_to, 'group_by' => $group_by, 'region' => $region_filter ?: null])) }}"
                            class="inline-flex items-center text-sm text-indigo-600 hover:underline"
                        >
                            Exportar CSV ↓
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                @if($group_by === 'user')
                                    Usuário responsável
                                @elseif($group_by === 'category')
                                    Categoria
                                @else
                                    Modelo
                                @endif
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Locações</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ticket médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rows as $row)
                            <tr class="text-sm">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row->grupo_nome }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $row->total_locacoes }}</td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-700">R$ {{ number_format($row->faturamento_total, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">R$ {{ number_format($row->ticket_medio, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    Nenhuma locação concluída com faturamento no período selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-gray-400">
                Considera locações concluídas pela data de conclusão. O responsável comercial é definido automaticamente ao abrir a ficha e pode ser transferido após a conclusão por gestor ou administrador.
            </p>
        </div>
    </div>
</div>
