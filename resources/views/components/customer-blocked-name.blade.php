@props([
    'name',
    'blocked' => false,
    'reason' => null,
    'href' => null,
    'tabTitle' => null,
    'class' => '',
])

@php
    $nameClass = $blocked
        ? 'font-medium text-red-700 hover:text-red-800'
        : 'font-medium text-indigo-600 hover:underline';
@endphp

@if($href)
    <a
        href="{{ $href }}"
        wire:navigate
        @if($tabTitle) data-tab-title="{{ $tabTitle }}" @elseif($href) data-tab-title="{{ $name }}" @endif
        @class([$nameClass, $class])
        @if($blocked && $reason) title="{{ $reason }}" @endif
    >
        {{ $name }}
        @if($blocked)
            <span class="ml-1 inline-flex align-middle text-[10px] font-bold uppercase tracking-wide text-red-600" title="{{ $reason }}">Bloqueado</span>
        @endif
    </a>
@else
    <span
        @class([$blocked ? 'font-medium text-red-700' : 'font-medium text-gray-900', $class])
        @if($blocked && $reason) title="{{ $reason }}" @endif
    >
        {{ $name }}
        @if($blocked)
            <span class="ml-1 inline-flex align-middle text-[10px] font-bold uppercase tracking-wide text-red-600">Bloqueado</span>
        @endif
    </span>
@endif

@if($blocked && $reason)
    <p class="text-xs text-red-600 mt-0.5">{{ $reason }}</p>
@endif
