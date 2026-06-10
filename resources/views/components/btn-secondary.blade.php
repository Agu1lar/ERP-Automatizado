@props(['type' => 'button'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2']) }}
>
    {{ $slot }}
</button>
