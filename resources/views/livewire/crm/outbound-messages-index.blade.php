<x-flash-message />

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('crm.pipeline') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Pipeline</a>
                <h2 class="text-2xl font-bold text-gray-800 mt-1">Fila de mensagens</h2>
                <p class="text-sm text-gray-500">WhatsApp e SMS — driver: {{ config('crm.messaging.driver') }}</p>
            </div>
            <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm">
                <option value="">Todos os status</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Data</th>
                        <th class="px-4 py-2 text-left">Cliente</th>
                        <th class="px-4 py-2 text-left">Canal</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Mensagem</th>
                        <th class="px-4 py-2 text-left">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($messages as $msg)
                        <tr wire:key="msg-{{ $msg->id }}">
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $msg->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $msg->customer?->nome ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $msg->channelEnum()->label() }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-amber-100 text-amber-800' => $msg->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $msg->status === 'sent',
                                    'bg-red-100 text-red-800' => $msg->status === 'failed',
                                ])>{{ $msg->statusEnum()->label() }}</span>
                            </td>
                            <td class="px-4 py-2 max-w-xs truncate" title="{{ $msg->body }}">{{ Str::limit($msg->body, 60) }}</td>
                            <td class="px-4 py-2">
                                @if($msg->channel === 'whatsapp')
                                    <a href="{{ $buildWhatsAppLink($msg) }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline text-xs">Abrir WhatsApp</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3">{{ $messages->links() }}</div>
        </div>
    </div>
</div>
