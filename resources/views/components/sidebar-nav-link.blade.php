@props([
    'active' => false,
    'badge' => null,
    'badgeColor' => 'bg-red-500',
    'href' => '#',
])

@php
    $classes = $active
        ? 'flex items-center justify-between gap-2 rounded-lg border-l-2 border-indigo-600 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-800'
        : 'flex items-center justify-between gap-2 rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900';
@endphp

<a href="{{ $href }}" {{ $attributes->except(['href', 'active', 'badge', 'badgeColor'])->merge(['class' => $classes]) }}>
    <span class="truncate">{{ $slot }}</span>
    @if(filled($badge))
        <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-bold text-white {{ $badgeColor }}">{{ $badge }}</span>
    @endif
</a>
