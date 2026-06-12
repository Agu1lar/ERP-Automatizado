<x-flash-message />

<div class="py-8">
    <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Pipeline comercial</h2>
                <p class="text-sm text-gray-500">Lead → qualificação → proposta → negociação. Orçamentos sincronizam automaticamente.</p>
                @if($dueCount > 0)
                    <p class="text-sm text-amber-700 mt-1">{{ $dueCount }} follow-up(s) vencido(s) hoje ou antes.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar..." class="rounded-md border-gray-300 text-sm shadow-sm">
                @if($canManage)
                    <button type="button" wire:click="openLeadForm" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Novo lead</button>
                @endif
                <a href="{{ route('crm.inactive') }}" wire:navigate class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Clientes inativos</a>
                <a href="{{ route('crm.messages') }}" wire:navigate class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Mensagens</a>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-4">
            @foreach($stages as $stage)
                @php $items = $pipeline[$stage->value] ?? collect(); @endphp
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 min-h-[20rem]">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $stage->label() }}</h3>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-600 border">{{ $items->count() }}</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($items as $opp)
                            <div wire:key="opp-{{ $opp->id }}" class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                                <p class="font-medium text-sm text-gray-900">{{ $opp->titulo }}</p>
                                <a href="{{ route('customers.show', $opp->customer) }}" wire:navigate class="text-xs text-indigo-600 hover:underline">{{ $opp->customer?->nome }}</a>
                                @if($opp->valor_estimado)
                                    <p class="text-xs text-gray-600 mt-1">R$ {{ number_format($opp->valor_estimado, 2, ',', '.') }}</p>
                                @endif
                                @if($opp->proximo_follow_up_em)
                                    <p class="text-xs mt-1 {{ $opp->proximo_follow_up_em->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                        Follow-up: {{ $opp->proximo_follow_up_em->format('d/m/Y') }}
                                    </p>
                                @endif
                                @if($opp->rental_quote_id)
                                    <a href="{{ route('quotes.index', ['search' => $opp->rentalQuote?->codigo]) }}" wire:navigate class="text-xs text-emerald-700 hover:underline block mt-1">{{ $opp->rentalQuote?->codigo }}</a>
                                @endif
                                @if($canManage)
                                    <div class="flex flex-wrap gap-1 mt-2 pt-2 border-t border-gray-100">
                                        @php
                                            $stageIndex = array_search($stage, $stages, true);
                                            $next = $stages[$stageIndex + 1] ?? null;
                                        @endphp
                                        @if($next)
                                            <button type="button" wire:click="advanceStage({{ $opp->id }}, '{{ $next->value }}')" class="text-[10px] rounded bg-indigo-50 text-indigo-700 px-2 py-1 hover:bg-indigo-100">→ {{ $next->label() }}</button>
                                        @else
                                            <button type="button" wire:click="advanceStage({{ $opp->id }}, 'ganho')" class="text-[10px] rounded bg-emerald-50 text-emerald-700 px-2 py-1 hover:bg-emerald-100">Ganho</button>
                                        @endif
                                        <button type="button" wire:click="openLostModal({{ $opp->id }})" class="text-[10px] rounded bg-red-50 text-red-700 px-2 py-1 hover:bg-red-100">Perdido</button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-6">Nenhuma oportunidade</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($showLeadForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Novo lead</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Buscar cliente</label>
                    <input type="text" wire:model.live.debounce.300ms="customer_search" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nome ou telefone">
                    @if($customerOptions->isNotEmpty())
                        <div class="mt-2 max-h-40 overflow-y-auto rounded border border-gray-200">
                            @foreach($customerOptions as $c)
                                <button type="button" wire:click="$set('customer_id', {{ $c->id }})" @class(['block w-full text-left px-3 py-2 text-sm hover:bg-indigo-50', 'bg-indigo-50' => $customer_id === $c->id])>
                                    {{ $c->nome }} · {{ $c->telefone }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('customer_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Título</label>
                    <input type="text" wire:model="titulo" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    @error('titulo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrição</label>
                    <textarea wire:model="descricao" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor estimado</label>
                        <input type="number" step="0.01" wire:model="valor_estimado" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Próximo follow-up</label>
                        <input type="date" wire:model="proximo_follow_up_em" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showLeadForm', false)" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                    <button type="button" wire:click="saveLead" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Salvar</button>
                </div>
            </div>
        </div>
    @endif

    @if($lostOpportunityId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Marcar como perdido</h3>
                <textarea wire:model="lost_reason" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Motivo da perda"></textarea>
                @error('lost_reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('lostOpportunityId', null)" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                    <button type="button" wire:click="markLost" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white">Confirmar</button>
                </div>
            </div>
        </div>
    @endif
</div>
