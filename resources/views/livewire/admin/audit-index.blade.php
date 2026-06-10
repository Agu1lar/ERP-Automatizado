<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <h2 class="text-xl font-semibold text-gray-800">Auditoria</h2>

            <div class="flex flex-wrap gap-4">
                <select wire:model.live="entidadeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todas entidades</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity }}">{{ $entity }}</option>
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
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entidade</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($logs as $log)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $log->entidade }} #{{ $log->entidade_id }}</td>
                                <td class="px-4 py-3">{{ $log->acao }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                                    @if($log->antes_json || $log->depois_json)
                                        {{ json_encode($log->depois_json ?? $log->antes_json, JSON_UNESCAPED_UNICODE) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</div>
