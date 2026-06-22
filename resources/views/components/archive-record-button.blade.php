@props(['model'])

@if (method_exists($model, 'trashed') && $model->trashed())
    @can('restore', $model)
        <button
            type="button"
            wire:click="restoreRecord({{ $model->id }}, @js($model::class))"
            class="text-emerald-600 hover:underline"
        >
            Restaurar
        </button>
    @endcan
@else
    @can('delete', $model)
        <button
            type="button"
            wire:click="archiveRecord({{ $model->id }}, @js($model::class))"
            wire:confirm="Arquivar este registro? Ele ficará oculto e poderá ser restaurado em até {{ config('archive.retention_days', 30) }} dias. Depois disso será excluído definitivamente."
            class="text-red-600 hover:underline"
        >
            Arquivar
        </button>
    @endcan
@endif
