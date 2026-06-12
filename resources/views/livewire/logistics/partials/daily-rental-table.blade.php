@php
    use App\Enums\LogisticsShift;

    $shiftLabel = fn (?string $value) => $value ? (LogisticsShift::tryFrom($value)?->label() ?? $value) : '—';
@endphp

<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-800">{{ $title }} <span class="text-gray-400 font-normal">({{ $rows->count() }})</span></h3>
    </div>
    @if($rows->isEmpty())
        <p class="px-4 py-6 text-sm text-gray-500">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @if($kind !== 'retorno')
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Turno</th>
                        @endif
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Locação</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pátio origem</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Local obra</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Região</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Observações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($rows as $rental)
                        @php
                            $turno = match ($kind) {
                                'entrega', 'cliente_retira' => $rental->entrega_turno,
                                'retirada', 'cliente_devolve' => $rental->retirada_turno,
                                default => null,
                            };
                            $obs = match ($kind) {
                                'entrega', 'cliente_retira' => $rental->entrega_observacoes,
                                'retirada', 'cliente_devolve' => $rental->retirada_observacoes,
                                default => $rental->observacoes,
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/80 text-sm">
                            @if($kind !== 'retorno')
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $shiftLabel($turno) }}</td>
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">{{ $rental->codigo }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $rental->customer->nome }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $rental->asset->codigo_patrimonio }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $rental->asset->yard?->displayLabel() ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 max-w-[14rem] truncate" title="{{ $rental->local_obra }}">{{ $rental->local_obra ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $rental->regionEnum()->shortLabel() }}</td>
                            <td class="px-4 py-3 text-gray-500 max-w-[12rem] truncate" title="{{ $obs }}">{{ $obs ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
