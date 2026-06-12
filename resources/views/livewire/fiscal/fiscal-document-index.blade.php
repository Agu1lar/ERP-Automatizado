<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Documentos fiscais (ponte ERP)</h2>
                    <p class="text-sm text-gray-500 mt-0.5">NFS-e e serviços registrados ao faturar — emissão no Omie/Bling, não neste sistema</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('finance.receivables') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Títulos</a>
                    @can('create', \App\Models\Domain\Fiscal\FiscalDocument::class)
                        <button wire:click="pushToOmie" wire:confirm="Enviar documentos pendentes ao Omie?" class="btn-primary text-sm inline-flex items-center px-3 py-2 rounded-md">Enviar pendentes ao Omie</button>
                    @endcan
                </div>
            </div>

            <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">Todos os status</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locação / Título</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ERP</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($documents as $document)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $document->codigo }}</td>
                                <td class="px-4 py-3">{{ $document->typeEnum()->label() }}</td>
                                <td class="px-4 py-3">
                                    @if($document->rental)
                                        <a href="{{ route('rentals.show', $document->rental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $document->rental->codigo }}</a>
                                    @endif
                                    @if($document->receivableTitle)
                                        <span class="text-gray-500 text-xs block">{{ $document->receivableTitle->codigo }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">R$ {{ number_format($document->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $document->statusEnum()->label() }}</td>
                                <td class="px-4 py-3 text-xs">
                                    {{ strtoupper($document->erp_provider) }}
                                    @if($document->erp_external_id)
                                        <span class="block text-gray-500">{{ $document->erp_external_id }}</span>
                                    @endif
                                    @if($document->erro_mensagem)
                                        <span class="block text-red-600">{{ Str::limit($document->erro_mensagem, 60) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $document)
                                        @if(in_array($document->status, ['pendente', 'enviado_erp']))
                                            <button wire:click="markEmitted({{ $document->id }})" class="text-emerald-600 hover:underline text-xs">Marcar emitido</button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum documento fiscal registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $documents->links() }}
        </div>
    </div>
</div>
