<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Copiloto — métricas e custo LLM</h2>
                    <p class="text-sm text-gray-500 mt-1">Tokens, custo estimado, fallback e comandos no período.</p>
                </div>
                <a href="{{ route('admin.agent-logs.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Ver logs de comandos</a>
            </div>

            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">De</label>
                    <input wire:model.live="dateFrom" type="date" class="rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Até</label>
                    <input wire:model.live="dateTo" type="date" class="rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Usuário</label>
                    <select wire:model.live="userFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Empresa operacional</label>
                    <select wire:model.live="operatingCompanyFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Todas</option>
                        @foreach($operatingCompanies as $company)
                            <option value="{{ $company->id }}">{{ $company->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @php
                $today = $metrics['today'];
                $llm = $metrics['llm'];
                $commands = $metrics['commands'];
            @endphp

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs uppercase text-gray-500">Hoje — tokens</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($today['tokens'], 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500 mt-1">~US$ {{ number_format($today['estimated_cost_usd'], 4, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs uppercase text-gray-500">Taxa fallback</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $llm['fallback_rate_percent'] !== null ? $llm['fallback_rate_percent'].'%' : '—' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $llm['fallback_events'] }} evento(s) no período</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs uppercase text-gray-500">Sucesso LLM</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $llm['success_rate_percent'] !== null ? $llm['success_rate_percent'].'%' : '—' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $llm['interpret_attempts'] }} tentativa(s)</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs uppercase text-gray-500">Negados (permissão)</p>
                    <p class="text-2xl font-semibold text-red-700">{{ $commands['permission_denied'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $commands['executed'] }} comando(s) OK</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="font-medium text-gray-800">Comandos mais usados</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse($commands['top_commands'] as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['command'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-6 text-gray-500 text-center">Sem dados no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="font-medium text-gray-800">Falhas por código</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse($commands['failures_by_error_code'] as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs {{ $row['error_code'] === 'forbidden' ? 'text-red-700' : 'text-gray-700' }}">{{ $row['error_code'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $row['count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-6 text-gray-500 text-center">Nenhuma falha registrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="font-medium text-gray-800">Uso por usuário (tokens)</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse($llm['usage_by_user'] as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['user_name'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($row['tokens'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">US$ {{ number_format($row['cost_usd'], 4, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-gray-500 text-center">Sem chamadas LLM.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="font-medium text-gray-800">Uso por empresa operacional</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse($llm['usage_by_operating_company'] as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['company_name'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($row['tokens'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">US$ {{ number_format($row['cost_usd'], 4, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-gray-500 text-center">Sem chamadas LLM.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($llm['failure_reasons'] !== [])
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-800 mb-2">Motivos de falha LLM</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($llm['failure_reasons'] as $row)
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800">
                                {{ $row['reason'] }}: {{ $row['count'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
