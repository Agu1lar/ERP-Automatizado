@props([
    'label',
    'display' => null,
    'type' => 'text',
    'editable' => false,
    'save' => null,
    'warning' => false,
    'warningMessage' => '',
    'placeholder' => 'Clique para preencher',
    'rows' => 2,
    'empty' => false,
])

@php
    $isEmpty = $empty || ($display === null || $display === '' || $display === '—');
    $inputType = match ($type) {
        'number', 'currency' => 'number',
        'date' => 'date',
        'email' => 'email',
        default => 'text',
    };
    $step = match ($type) {
        'currency' => '0.01',
        'number' => '0.01',
        default => null,
    };
@endphp

<div
    x-data="{ editing: false }"
    @if($editable)
        @keydown.escape.window="if (editing) { editing = false; }"
    @endif
    class="group rounded-lg border border-transparent px-3 py-2.5 transition {{ $editable ? 'hover:border-indigo-100 hover:bg-indigo-50/50' : '' }}"
>
    <div class="mb-1 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500">
        <span>{{ $label }}</span>
        @if($warning)
            <span
                class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white"
                title="{{ $warningMessage }}"
            >!</span>
        @endif
        @if($editable)
            <svg class="h-3 w-3 text-indigo-400 opacity-0 transition group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
            </svg>
            @if($save)
                <span wire:loading wire:target="{{ $save }}" class="text-[10px] font-normal normal-case text-indigo-500">salvando…</span>
            @endif
        @endif
    </div>

    @if($editable)
        <div x-show="!editing" x-cloak @click="editing = true" class="min-h-[1.375rem] cursor-text">
            @if($isEmpty)
                <span class="text-sm italic text-gray-400">{{ $placeholder }}</span>
            @else
                <span class="text-sm text-gray-900 border-b border-dashed border-gray-300 group-hover:border-indigo-400">{{ $display }}</span>
            @endif
        </div>

        <div x-show="editing" x-cloak @click.outside="editing = false" class="mt-0.5">
            @if($type === 'textarea')
                <textarea
                    {{ $attributes->whereStartsWith('wire:model') }}
                    @if($save) wire:blur="{{ $save }}" @endif
                    rows="{{ $rows }}"
                    x-init="$nextTick(() => { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length) })"
                    @keydown.escape.prevent="editing = false"
                    class="w-full rounded-md border-indigo-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
            @elseif($type === 'select')
                <select
                    {{ $attributes->whereStartsWith('wire:model') }}
                    @if($save) wire:change="{{ $save }}" @endif
                    x-init="$nextTick(() => $el.focus())"
                    class="w-full rounded-md border-indigo-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    {{ $slot }}
                </select>
            @else
                <input
                    type="{{ $inputType }}"
                    @if($step) step="{{ $step }}" @endif
                    @if(in_array($type, ['number', 'currency'])) min="0" @endif
                    {{ $attributes->whereStartsWith('wire:model') }}
                    @if($save) wire:blur="{{ $save }}" wire:keydown.enter.prevent="{{ $save }}" @endif
                    x-init="$nextTick(() => { $el.focus(); if ($el.select) $el.select() })"
                    @keydown.escape.prevent="editing = false"
                    class="w-full rounded-md border-indigo-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            @endif
            <p class="mt-1 text-[10px] text-gray-400">Clique fora ou Enter para salvar · Esc cancela</p>
        </div>
    @else
        <div class="text-sm text-gray-900">
            @if($isEmpty)
                <span class="text-gray-400">—</span>
            @else
                {{ $display }}
            @endif
        </div>
    @endif
</div>
