<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <a href="{{ route('maintenance.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $order->codigo }}</h2>
                    <p class="text-gray-500">{{ $order->asset->codigo_patrimonio }} — {{ $order->tipoEnum()->label() }} ({{ $order->prioridadeEnum()->label() }})</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('maintenance.pdf', $order) }}" target="_blank" class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Baixar PDF</a>
                    <x-status-badge :status="$status" />
                    @if($order->impeditiva)
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Impeditiva</span>
                    @endif
                    @if($status === \App\Enums\MaintenanceOrderStatus::Aberta)
                        @can('operate', $order)
                            <x-btn-primary wire:click="start">Iniciar execução</x-btn-primary>
                            <x-btn-secondary wire:click="openCancelModal">Cancelar OS</x-btn-secondary>
                        @endcan
                    @elseif($status === \App\Enums\MaintenanceOrderStatus::EmExecucao)
                        @can('operate', $order)
                            <x-btn-secondary wire:click="openWaitModal">Aguardando peça</x-btn-secondary>
                            <x-btn-primary wire:click="openCompleteModal">Concluir OS</x-btn-primary>
                        @endcan
                    @elseif($status === \App\Enums\MaintenanceOrderStatus::AguardandoPeca)
                        @can('operate', $order)
                            <x-btn-primary wire:click="resume">Retomar execução</x-btn-primary>
                            <x-btn-secondary wire:click="openCompleteModal">Concluir OS</x-btn-secondary>
                        @endcan
                    @endif
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                    <h3 class="font-semibold text-gray-800">Dados da OS</h3>
                    <div><span class="text-gray-500">Patrimônio:</span>
                        <a href="{{ route('assets.show', $order->asset) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $order->asset->codigo_patrimonio }}</a>
                    </div>
                    <div><span class="text-gray-500">Status patrimônio:</span> <x-status-badge :status="$order->asset->statusEnum()" /></div>
                    @if($order->rental)
                        <div><span class="text-gray-500">Locação vinculada:</span>
                            <a href="{{ route('rentals.show', $order->rental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $order->rental->codigo }}</a>
                        </div>
                    @endif
                    <div><span class="text-gray-500">Aberta em:</span> {{ $order->opened_at->format('d/m/Y H:i') }} — {{ $order->openedByUser?->name ?? 'Sistema' }}</div>
                    @if($order->started_at)
                        <div><span class="text-gray-500">Iniciada em:</span> {{ $order->started_at->format('d/m/Y H:i') }}</div>
                    @endif
                    @if($order->completed_at)
                        <div><span class="text-gray-500">Concluída em:</span> {{ $order->completed_at->format('d/m/Y H:i') }} — {{ $order->completedByUser?->name ?? 'Sistema' }}</div>
                    @endif
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-gray-500 font-medium">Problema relatado:</span>
                        <p class="mt-1 text-gray-700 whitespace-pre-line">{{ $order->descricao_problema }}</p>
                    </div>
                    @if($status->isOpen())
                        @can('update', $order)
                            @php
                                $customerDisplay = $customers->firstWhere('id', $customer_id)?->nome ?? $order->resolvedCustomer()?->nome;
                                $technicianDisplay = $technicians->firstWhere('id', $assigned_to)?->name;
                            @endphp
                            <div class="pt-3 border-t border-gray-100">
                                <p class="text-xs text-gray-400 mb-3">Clique em qualquer campo para editar e salvar</p>
                                <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                                    <x-inline-field label="Cliente (PDF)" :display="$customerDisplay" type="select" :editable="true" save="saveTechnicalData" wire:model="customer_id">
                                        <option value="">— Nenhum —</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->nome }}</option>
                                        @endforeach
                                    </x-inline-field>
                                    <x-inline-field label="Voltagem" :display="$order->asset->voltagem" :editable="true" save="saveTechnicalData" wire:model="asset_voltagem" placeholder="Ex.: 220V" />
                                    <div class="md:col-span-2">
                                        <x-inline-field label="Parecer técnico (PDF)" :display="$order->parecer_tecnico" type="textarea" :rows="4" :editable="true" save="saveTechnicalData" wire:model="parecer_tecnico" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-inline-field label="Diagnóstico" :display="$order->diagnostico" type="textarea" :editable="true" save="saveTechnicalData" wire:model="diagnostico" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-inline-field label="Solução aplicada" :display="$order->solucao_aplicada" type="textarea" :editable="true" save="saveTechnicalData" wire:model="solucao_aplicada" />
                                    </div>
                                    <x-inline-field label="Assinatura — Caixa" :display="$order->assinatura_caixa" :editable="true" save="saveTechnicalData" wire:model="assinatura_caixa" />
                                    <x-inline-field label="Orçado por" :display="$order->assinatura_orcado_por" :editable="true" save="saveTechnicalData" wire:model="assinatura_orcado_por" />
                                    <x-inline-field label="Montado por" :display="$order->assinatura_montado_por" :editable="true" save="saveTechnicalData" wire:model="assinatura_montado_por" />
                                    <x-inline-field label="Responsável" :display="$technicianDisplay" type="select" :editable="true" save="saveTechnicalData" wire:model="assigned_to">
                                        <option value="">Não atribuído</option>
                                        @foreach($technicians as $tech)
                                            <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                        @endforeach
                                    </x-inline-field>
                                    <x-inline-field
                                        label="Previsão de conclusão"
                                        :display="$order->expected_completion_at?->format('d/m/Y')"
                                        type="date"
                                        :editable="true"
                                        save="saveTechnicalData"
                                        wire:model="expected_completion_at"
                                    />
                                </div>
                            </div>
                        @endcan
                    @endif

                    <livewire:custom-field.custom-field-panel :entity-type="'maintenance_order'" :entity-id="$order->id" :inline="true" :key="'cf-os-'.$order->id" />
                </div>

                @if(! $status->isOpen())
                    <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                        <h3 class="font-semibold text-gray-800">Registro técnico</h3>
                        @if($order->resolvedCustomer())
                            <div><span class="text-gray-500">Cliente:</span> {{ $order->resolvedCustomer()->nome }}</div>
                        @endif
                        @if($order->asset->voltagem)
                            <div><span class="text-gray-500">Voltagem:</span> {{ $order->asset->voltagem }}</div>
                        @endif
                        @if($order->parecer_tecnico)
                            <div><span class="text-gray-500">Parecer técnico:</span> <span class="whitespace-pre-line">{{ $order->parecer_tecnico }}</span></div>
                        @endif
                        <div><span class="text-gray-500">Diagnóstico:</span> {{ $order->diagnostico ?? '—' }}</div>
                        <div><span class="text-gray-500">Solução:</span> {{ $order->solucao_aplicada ?? '—' }}</div>
                        @if($order->cancel_reason)
                            <div class="text-red-600"><span class="font-medium">Cancelamento:</span> {{ $order->cancel_reason }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800">Peças</h3>
                        <span class="text-sm text-gray-500">Total: R$ {{ number_format($order->totalPartsCost(), 2, ',', '.') }}</span>
                    </div>
                    @if($status->isOpen())
                        @can('update', $order)
                            <form wire:submit="addPart" class="space-y-3 mb-4 pb-4 border-b border-gray-100 text-sm">
                                <div>
                                    <input wire:model.live.debounce.300ms="part_search" type="text" placeholder="Buscar no catálogo (código ou descrição)..." class="w-full rounded-md border-gray-300 shadow-sm" />
                                    @if(count($partSuggestions) > 0)
                                        <ul class="mt-1 border border-gray-200 rounded-md divide-y divide-gray-100 bg-white shadow-sm">
                                            @foreach($partSuggestions as $suggestion)
                                                <li>
                                                    <button type="button" wire:click="pickCatalogPart({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50 text-xs">
                                                        <span class="font-medium text-indigo-700">{{ $suggestion['codigo_peca'] }}</span>
                                                        — {{ $suggestion['descricao'] }}
                                                        @if($suggestion['valor']) <span class="text-gray-400">R$ {{ number_format($suggestion['valor'], 2, ',', '.') }}</span>@endif
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="grid md:grid-cols-2 gap-3">
                                    <input wire:model.live.debounce.400ms="part_codigo_peca" type="text" placeholder="Código da peça" class="rounded-md border-gray-300 shadow-sm" />
                                    <input wire:model="part_codigo_alternativo" type="text" placeholder="Código alternativo" class="rounded-md border-gray-300 shadow-sm" />
                                    <input wire:model="part_descricao" type="text" placeholder="Descrição da peça *" class="rounded-md border-gray-300 shadow-sm md:col-span-2" />
                                    <input wire:model="part_quantidade" type="number" step="0.01" min="0.01" placeholder="Qtd" class="rounded-md border-gray-300 shadow-sm" />
                                    <input wire:model="part_valor_unitario" type="number" step="0.01" min="0" placeholder="Valor unit." class="rounded-md border-gray-300 shadow-sm" />
                                </div>
                                @error('part_descricao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                <x-btn-secondary type="submit">Adicionar peça</x-btn-secondary>
                            </form>
                        @endcan
                    @endif
                    <div class="space-y-2 text-sm">
                        @forelse($order->parts as $part)
                            <div class="flex justify-between items-start gap-2 py-2 border-b border-gray-50 last:border-0">
                                <div>
                                    <span class="font-medium">{{ $part->descricao }}</span>
                                    <p class="text-gray-500 text-xs">{{ $part->quantidade }} x R$ {{ number_format($part->valor_unitario ?? 0, 2, ',', '.') }} = R$ {{ number_format($part->subtotal(), 2, ',', '.') }}</p>
                                </div>
                                @if($status->isOpen())
                                    @can('update', $order)
                                        <button wire:click="removePart({{ $part->id }})" class="text-red-600 text-xs hover:underline">Remover</button>
                                    @endcan
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">Nenhuma peça registrada.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800">Horas de trabalho</h3>
                        <span class="text-sm text-gray-500">Total: {{ number_format($order->totalLaborHours(), 2, ',', '.') }} h</span>
                    </div>
                    @if($status->isOpen())
                        @can('update', $order)
                            <form wire:submit="addLaborHour" class="space-y-3 mb-4 pb-4 border-b border-gray-100 text-sm">
                                <div class="grid md:grid-cols-2 gap-3">
                                    <input wire:model="labor_data" type="date" class="rounded-md border-gray-300 shadow-sm" />
                                    <input wire:model="labor_horas" type="number" step="0.25" min="0.01" placeholder="Horas *" class="rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <input wire:model="labor_descricao" type="text" placeholder="Atividade realizada *" class="w-full rounded-md border-gray-300 shadow-sm" />
                                <select wire:model="labor_user_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                @error('labor_descricao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                <x-btn-secondary type="submit">Registrar horas</x-btn-secondary>
                            </form>
                        @endcan
                    @endif
                    <div class="space-y-2 text-sm">
                        @forelse($order->laborHours as $hour)
                            <div class="flex justify-between items-start gap-2 py-2 border-b border-gray-50 last:border-0">
                                <div>
                                    <span class="font-medium">{{ $hour->descricao_atividade }}</span>
                                    <p class="text-gray-500 text-xs">{{ $hour->data->format('d/m/Y') }} — {{ $hour->horas }} h — {{ $hour->user?->name ?? 'Sistema' }}</p>
                                </div>
                                @if($status->isOpen())
                                    @can('update', $order)
                                        <button wire:click="removeLaborHour({{ $hour->id }})" class="text-red-600 text-xs hover:underline">Remover</button>
                                    @endcan
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">Nenhuma hora registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($showWaitModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Aguardando peça</h3>
                <form wire:submit="waitForPart" class="space-y-4">
                    <textarea wire:model="wait_observacao" rows="3" placeholder="Observação (opcional)" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="$set('showWaitModal', false)">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Confirmar</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCompleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Concluir OS</h3>
                <form wire:submit="complete" class="space-y-4">
                    <textarea wire:model="complete_solucao" rows="4" placeholder="Solução aplicada" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    @error('complete_solucao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="$set('showCompleteModal', false)">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Concluir</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Cancelar OS</h3>
                <form wire:submit="cancel" class="space-y-4">
                    <textarea wire:model="cancel_reason" rows="3" placeholder="Motivo do cancelamento *" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    @error('cancel_reason') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="$set('showCancelModal', false)">Voltar</x-btn-secondary>
                        <x-btn-primary type="submit">Confirmar cancelamento</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
