@props([
    'id',
    'label',
    'active' => false,
    'badge' => null,
    'badgeColor' => 'bg-red-500',
])

<div class="mb-0.5">
    <button
        type="button"
        data-label="{{ $label }}"
        @mouseenter="showFlyout('{{ $id }}', $event)"
        @mouseleave="scheduleClose()"
        @click="toggleGroup('{{ $id }}', $event)"
        @class([
            'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide transition',
            'bg-indigo-50 text-indigo-700' => $active,
            'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $active,
        ])
    >
        <span class="flex min-w-0 items-center gap-2">
            <span class="truncate">{{ $label }}</span>
            @if(filled($badge))
                <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-bold text-white {{ $badgeColor }}">{{ $badge }}</span>
            @endif
        </span>
        <svg class="hidden h-3.5 w-3.5 shrink-0 text-gray-400 lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 lg:hidden" :class="mobileOpen('{{ $id }}', @json($active)) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Mobile: acordeão inline --}}
    <div
        x-show="mobileOpen('{{ $id }}', @json($active))"
        x-cloak
        class="mt-0.5 space-y-0.5 border-l-2 border-indigo-100 py-1 ps-2 ms-3 lg:hidden"
    >
        {{ $slot }}
    </div>
</div>

@push('sidebar-flyouts')
    <div
        x-show="isDesktop && hoveredGroup === '{{ $id }}'"
        x-cloak
        class="space-y-0.5"
        data-nav-flyout-panel="{{ $id }}"
    >
        {{ $slot }}
    </div>
@endpush
