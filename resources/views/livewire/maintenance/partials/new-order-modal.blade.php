@if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:keydown.escape="cancelForm">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="new-order-modal-title">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 id="new-order-modal-title" class="text-lg font-semibold text-gray-900 inline-flex items-center">
                        Nova ordem de serviço
                        <x-help-hint text="Busque o patrimônio pelo código, descreva o defeito e defina prioridade. Após abrir, registre peças e horas na ficha da OS." />
                    </h3>
                    <p class="text-sm text-gray-500 mt-0.5">Informe o patrimônio — cliente e equipamento serão identificados automaticamente</p>
                </div>
                <button type="button" wire:click="cancelForm" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Fechar">&times;</button>
            </div>

            <form wire:submit="save" class="p-6 space-y-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Patrimônio *</label>
                    <input
                        wire:model.live.debounce.400ms="asset_search"
                        type="text"
                        placeholder="Ex: PAT-0001"
                        class="w-full rounded-md border-gray-300 shadow-sm"
                        autocomplete="off"
                    />
                    @error('asset_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    @if($assetResolveMessage)
                        <p class="text-sm text-amber-600">{{ $assetResolveMessage }}</p>
                    @endif

                    @if(count($assetSuggestions) > 0)
                        <ul class="border border-gray-200 rounded-md divide-y divide-gray-100 text-sm bg-white shadow-sm max-h-48 overflow-y-auto">
                            @foreach($assetSuggestions as $suggestion)
                                <li>
                                    <button type="button" wire:click="pickAsset({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50 flex justify-between gap-2">
                                        <span><span class="font-medium text-indigo-700">{{ $suggestion['codigo'] }}</span> — {{ $suggestion['modelo'] }}</span>
                                        <span class="text-xs {{ $suggestion['has_open_os'] ? 'text-red-600' : 'text-gray-500' }}">{{ $suggestion['status'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($assetPreview)
                        <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm space-y-2">
                            <p class="font-semibold text-indigo-900">{{ $assetPreview['codigo'] }} — {{ $assetPreview['modelo'] }}</p>
                            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1 text-gray-700">
                                <div><span class="text-gray-500">Marca:</span> {{ $assetPreview['marca'] }}</div>
                                <div><span class="text-gray-500">Voltagem:</span> {{ $assetPreview['voltagem'] ?? '—' }}</div>
                                <div><span class="text-gray-500">Horímetro:</span> {{ $assetPreview['horimetro'] !== null ? number_format($assetPreview['horimetro'], 2, ',', '.').' h' : '—' }}</div>
                                <div><span class="text-gray-500">Status:</span> {{ $assetPreview['status'] }}</div>
                                @if($assetPreview['cliente'])
                                    <div class="sm:col-span-2"><span class="text-gray-500">Cliente (locação):</span> {{ $assetPreview['cliente'] }} @if($assetPreview['locacao'])<span class="text-gray-400">({{ $assetPreview['locacao'] }})</span>@endif</div>
                                @endif
                            </div>
                            @if(count($assetPreview['recent_parts']) > 0)
                                <p class="text-xs text-indigo-700 pt-2 border-t border-indigo-100">Peças usadas recentemente: {{ implode(', ', $assetPreview['recent_parts']) }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select wire:model="tipo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @foreach($typeOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Prioridade</label>
                        <select wire:model="prioridade" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @foreach($priorityOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrição do problema *</label>
                    <textarea wire:model="descricao_problema" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    @error('descricao_problema') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Previsão de conclusão</label>
                        <input wire:model="expected_completion_at" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Responsável</label>
                        <select wire:model="assigned_to" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Não atribuído</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="impeditiva" type="checkbox" class="rounded border-gray-300" />
                    <span>OS impeditiva (bloqueia liberação do patrimônio)</span>
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex gap-2 justify-end pt-2 border-t border-gray-100">
                    <x-btn-secondary type="button" wire:click="cancelForm">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Abrir OS</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif
