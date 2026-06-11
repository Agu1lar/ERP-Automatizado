<div class="px-4 py-4 space-y-4 max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Patrimônio</p>
        <h1 class="text-xl font-bold text-gray-900">{{ $asset->codigo_patrimonio }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ $asset->equipmentDisplayName() }}</p>
        <p class="text-xs text-gray-500 mt-2">Status: {{ $asset->statusEnum()->label() }}</p>
        <a href="{{ route('assets.show', $asset) }}" wire:navigate class="inline-block mt-3 text-sm text-indigo-600 hover:underline">
            Ver ficha completa →
        </a>
    </div>

    @if($activeRental)
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Locação ativa</p>
            <p class="font-semibold text-gray-900">{{ $activeRental->codigo }}</p>
            <p class="text-sm text-gray-600">{{ $activeRental->customer?->nome }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $activeRental->statusEnum()->label() }}</p>
        </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-600">
            Nenhuma locação reservada ou locada para este patrimônio.
        </div>
    @endif

    @if(in_array($mode, ['checkout', 'return'], true) && auth()->user()?->can('rentals.operate'))
        <div
            class="bg-white rounded-xl shadow p-4 space-y-4"
            wire:ignore.self
            x-data="yardChecklist({
                storageKey: @js('yard-checklist-'.$asset->id.'-'.$mode),
                labels: @js($checklistLabels),
                submitAction: @js($mode === 'checkout' ? 'submitCheckout' : 'submitReturn'),
            })"
        >
            <div>
                <h2 class="font-semibold text-gray-900">
                    Checklist de {{ $mode === 'checkout' ? 'saída' : 'retorno' }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Marcações ficam salvas neste aparelho até você confirmar — sem depender da rede a cada toque.
                </p>
            </div>

            <div class="space-y-3">
                <template x-for="[key, label] in Object.entries(labels)" :key="key">
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                        <input
                            type="checkbox"
                            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            :checked="checklist[key]"
                            @change="checklist[key] = $event.target.checked; persist()"
                        />
                        <span class="text-sm text-gray-800" x-text="label"></span>
                    </label>
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                    <textarea
                        x-model="observacoes"
                        @input="persist()"
                        rows="2"
                        class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Opcional"
                    ></textarea>
                </div>

                <button
                    type="button"
                    @click="submit()"
                    wire:loading.attr="disabled"
                    wire:target="submitCheckout,submitReturn"
                    class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="submitCheckout,submitReturn">
                        Confirmar {{ $mode === 'checkout' ? 'saída' : 'retorno' }}
                    </span>
                    <span wire:loading wire:target="submitCheckout,submitReturn">Enviando…</span>
                </button>
            </div>
        </div>
    @elseif($activeRental && ! auth()->user()?->can('rentals.operate'))
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
            Você não tem permissão para registrar saída/retorno.
        </p>
    @endif
</div>
