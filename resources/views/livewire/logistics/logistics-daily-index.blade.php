@php
    use App\Enums\LogisticsShift;

    $shiftLabel = fn (?string $value) => $value ? (LogisticsShift::tryFrom($value)?->label() ?? $value) : '—';
@endphp

<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                        Lista do dia
                        <x-help-hint text="Proto-romaneio: viagens da empresa e movimentações no pátio (cliente retira/devolve). Ainda sem rota ou motorista." />
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">Logística RMBH — frota própria e retirada/devolução pelo cliente no pátio</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-btn-secondary type="button" wire:click="previousDay">← Anterior</x-btn-secondary>
                    <input wire:model.live="selectedDate" type="date" class="rounded-md border-gray-300 text-sm shadow-sm" />
                    <x-btn-secondary type="button" wire:click="nextDay">Próximo →</x-btn-secondary>
                    <x-btn-secondary type="button" wire:click="goToday">Hoje</x-btn-secondary>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Entregas (frota)</p>
                    <p class="text-2xl font-bold text-emerald-900">{{ $counts['entregas'] }}</p>
                </div>
                <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Cliente retira</p>
                    <p class="text-2xl font-bold text-teal-900">{{ $counts['cliente_retira'] }}</p>
                </div>
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Recolhidas (frota)</p>
                    <p class="text-2xl font-bold text-indigo-900">{{ $counts['retiradas'] }}</p>
                </div>
                <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Cliente devolve</p>
                    <p class="text-2xl font-bold text-cyan-900">{{ $counts['cliente_devolve'] }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Retornos s/ agenda</p>
                    <p class="text-2xl font-bold text-amber-900">{{ $counts['retornos_previstos'] }}</p>
                </div>
            </div>

            <p class="text-sm font-medium text-gray-700">{{ $date->translatedFormat('l, d \d\e F \d\e Y') }}</p>

            @include('livewire.logistics.partials.daily-rental-table', [
                'title' => 'Entregas pela frota',
                'empty' => 'Nenhuma entrega pela empresa nesta data.',
                'rows' => $deliveries,
                'kind' => 'entrega',
            ])

            @include('livewire.logistics.partials.daily-rental-table', [
                'title' => 'Cliente retira no pátio',
                'empty' => 'Nenhuma retirada pelo cliente agendada nesta data.',
                'rows' => $customerPickups,
                'kind' => 'cliente_retira',
            ])

            @include('livewire.logistics.partials.daily-rental-table', [
                'title' => 'Recolhidas pela frota',
                'empty' => 'Nenhuma recolhida pela empresa nesta data.',
                'rows' => $pickups,
                'kind' => 'retirada',
            ])

            @include('livewire.logistics.partials.daily-rental-table', [
                'title' => 'Cliente devolve no pátio',
                'empty' => 'Nenhuma devolução pelo cliente prevista nesta data.',
                'rows' => $customerReturns,
                'kind' => 'cliente_devolve',
            ])

            @include('livewire.logistics.partials.daily-rental-table', [
                'title' => 'Retornos previstos (frota, sem agenda)',
                'empty' => 'Nenhum retorno previsto sem agenda de recolhida.',
                'rows' => $expectedReturns,
                'kind' => 'retorno',
            ])
        </div>
    </div>
</div>
