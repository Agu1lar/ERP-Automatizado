@props(['status'])

@php
    $colors = [
        'green' => 'bg-green-100 text-green-800',
        'blue' => 'bg-blue-100 text-blue-800',
        'indigo' => 'bg-indigo-100 text-indigo-800',
        'yellow' => 'bg-yellow-100 text-yellow-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'red' => 'bg-red-100 text-red-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'gray' => 'bg-gray-100 text-gray-800',
        'slate' => 'bg-slate-100 text-slate-800',
        'zinc' => 'bg-zinc-100 text-zinc-800',
    ];
    $class = $colors[$status->color()] ?? 'bg-gray-100 text-gray-800';
@endphp

<span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $class }}">
    {{ $status->label() }}
</span>
