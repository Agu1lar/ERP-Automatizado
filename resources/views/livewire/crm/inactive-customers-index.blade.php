<x-flash-message />

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('crm.pipeline') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Pipeline</a>
                <h2 class="text-2xl font-bold text-gray-800 mt-1">Campanha — clientes inativos</h2>
                <p class="text-sm text-gray-500">{{ $totalInactive }} cliente(s) sem locação nos últimos {{ $months }} meses (com telefone).</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Meses sem locação</label>
                    <input type="number" min="1" max="24" wire:model.live="months" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Buscar</label>
                    <input type="search" wire:model.live.debounce.300ms="search" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nome ou telefone">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Canal</label>
                    <select wire:model="channel" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Mensagem (opcional — use {nome})</label>
                <textarea wire:model.live.debounce.500ms="custom_body" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="{{ config('crm.templates.inactive_campaign') }}"></textarea>
                @if($preview)
                    <p class="text-xs text-gray-500 mt-2">Prévia: <span class="text-gray-700">{{ $preview }}</span></p>
                @endif
            </div>

            @if($canManage)
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-indigo-600">
                        Selecionar todos ({{ $customers->count() }} na lista)
                    </label>
                    <button type="button" wire:click="queueCampaign" wire:confirm="Enfileirar mensagens para os clientes selecionados?" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Enfileirar campanha ({{ count($selected) }})
                    </button>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @if($canManage)<th class="px-4 py-2 w-10"></th>@endif
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Cliente</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Telefone</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Último contato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($customers as $customer)
                        <tr wire:key="inactive-{{ $customer->id }}">
                            @if($canManage)
                                <td class="px-4 py-2">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $customer->id }}" class="rounded border-gray-300 text-indigo-600">
                                </td>
                            @endif
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.show', $customer) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $customer->nome }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $customer->telefone }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $customer->ultimo_contato_em?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum cliente inativo encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
