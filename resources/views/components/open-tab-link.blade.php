@props(['href', 'label' => null])

<a
    href="{{ $href }}"
    wire:navigate
    data-tab-title="{{ $label }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-indigo-600 hover:underline']) }}
    title="{{ $label ? "Abrir {$label} em nova aba (Ctrl+clique)" : 'Ctrl+clique para nova aba' }}"
>
    {{ $slot->isEmpty() ? ($label ?? 'Abrir') : $slot }}
    <svg class="h-3.5 w-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
</a>
