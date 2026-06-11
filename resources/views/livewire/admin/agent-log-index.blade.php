<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Copiloto — auditoria de comandos</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Hoje: {{ $todayCount }} comando(s) executado(s), {{ $todayDryRun }} simulação(ões) (dry-run).
                </p>
            </div>

            <div class="flex flex-wrap gap-4 items-end">
                <select wire:model.live="commandFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos comandos</option>
                    @foreach($commands as $command)
                        <option value="{{ $command }}">{{ $command }}</option>
                    @endforeach
                </select>
                <select wire:model.live="userFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos usuários</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <input wire:model.live="dateFrom" type="date" class="rounded-md border-gray-300 shadow-sm text-sm" />
                <input wire:model.live="dateTo" type="date" class="rounded-md border-gray-300 shadow-sm text-sm" />
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input wire:model.live="dryRunOnly" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                    Somente dry-run
                </label>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comando</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <code class="text-xs text-indigo-700">{{ $log->command }}</code>
                                    @if($log->dry_run)
                                        <span class="ml-1 text-xs text-amber-600">dry-run</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->ok)
                                        <span class="text-emerald-600">OK</span>
                                    @else
                                        <span class="text-red-600">Erro</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 max-w-md truncate" title="{{ $log->result['message'] ?? '' }}">
                                    {{ $log->result['message'] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhum registro encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</div>
