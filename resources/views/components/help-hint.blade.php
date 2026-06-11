@props(['text'])

<span
    {{ $attributes->merge(['class' => 'relative inline-flex align-middle ml-1']) }}
    x-data="{ open: false }"
    @mouseenter="open = true"
    @mouseleave="open = false"
    @focusin="open = true"
    @focusout="open = false"
>
    <span
        class="inline-flex h-4 w-4 shrink-0 cursor-help items-center justify-center rounded-full border border-gray-300 bg-white text-[10px] font-bold leading-none text-gray-500 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-300"
        aria-label="Ajuda"
        tabindex="0"
        role="img"
    >?</span>
    <span
        x-show="open"
        x-transition.opacity
        x-cloak
        class="pointer-events-none absolute left-1/2 top-full z-50 mt-1.5 w-64 -translate-x-1/2 rounded-md border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-normal leading-snug text-gray-600 shadow-lg"
        role="tooltip"
    >{{ $text }}</span>
</span>
