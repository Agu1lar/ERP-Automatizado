@props(['label', 'field' => null, 'warnings' => []])

@php
    $hasWarning = $field && \App\Support\FichaCompleteness::hasFieldWarning($warnings, $field);
    $warningMessage = $hasWarning
        ? collect($warnings)->firstWhere('field', $field)['message'] ?? ''
        : '';
@endphp

<label class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
    <span>{{ $label }}</span>
    @if($hasWarning)
        <span
            class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white"
            title="{{ $warningMessage }}"
        >!</span>
    @endif
</label>
