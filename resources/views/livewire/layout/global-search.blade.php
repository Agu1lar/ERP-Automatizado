<div
    class="relative w-full max-w-xs lg:max-w-sm"
    x-data="{ open: @entangle('open') }"
    @click.outside="open = false; $wire.clear()"
>
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/>
        </svg>
        <input
            wire:model.live.debounce.250ms="query"
            type="search"
            placeholder="Buscar..."
            autocomplete="off"
            @focus="if ($wire.query.length >= 1) open = true"
            class="w-full rounded-lg border-gray-300 bg-gray-50 py-2 pl-9 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
        />
        @if($query !== '')
            <button
                type="button"
                wire:click="clear"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-gray-600"
                aria-label="Limpar busca"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
    </div>

    @if($open && strlen(trim($query)) >= 1)
        <div class="absolute right-0 z-50 mt-1 w-80 rounded-lg border border-gray-200 bg-white shadow-xl">
            @forelse($suggestions as $item)
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    @click="open = false"
                    class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-indigo-50"
                >
                    <span @class([
                        'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'bg-indigo-100 text-indigo-700' => $item['type'] === 'patrimonio',
                        'bg-emerald-100 text-emerald-700' => $item['type'] === 'modelo',
                        'bg-amber-100 text-amber-700' => $item['type'] === 'cliente',
                    ])>
                        {{ $item['type'] }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-gray-900">{{ $item['label'] }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ $item['subtitle'] }}</span>
                    </span>
                </a>
            @empty
                <p class="px-4 py-3 text-sm text-gray-500">Nenhum resultado para "{{ $query }}"</p>
            @endforelse
        </div>
    @endif
</div>
