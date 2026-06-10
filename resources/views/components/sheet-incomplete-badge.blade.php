@props(['warnings' => []])

@if(count($warnings) > 0)
    <span
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800']) }}
        title="{{ collect($warnings)->pluck('message')->implode(' · ') }}"
    >
        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white">!</span>
        Ficha incompleta
    </span>
@endif
