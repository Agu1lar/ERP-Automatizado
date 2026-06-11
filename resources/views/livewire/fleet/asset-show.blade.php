<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <a href="{{ route('assets.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $asset->codigo_patrimonio }}</h2>
                    <p class="text-gray-500">{{ $asset->equipmentModel?->category?->nome ?? '—' }} — {{ $asset->equipmentDisplayName() }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$currentStatus" />
                    <x-sheet-incomplete-badge :warnings="$fichaWarnings" />
                    <a href="{{ route('assets.print', $asset) }}" target="_blank" class="btn-secondary text-sm">Imprimir ficha</a>
                    <a href="{{ route('assets.pdf', $asset) }}" target="_blank" class="btn-secondary text-sm">Baixar PDF</a>
                    @can('changeStatus', $asset)
                        <x-btn-secondary wire:click="openLocationModal">Mover</x-btn-secondary>
                        <x-btn-primary wire:click="openStatusModal">Alterar status</x-btn-primary>
                    @endcan
                </div>
            </div>

            <div class="border-b border-gray-200">
                <nav class="flex gap-6">
                    @foreach(['resumo' => 'Resumo', 'manutencao' => 'Manutenção', 'timeline' => 'Histórico', 'anexos' => 'Anexos'] as $tab => $label)
                        <button wire:click="$set('activeTab', '{{ $tab }}')" class="py-2 text-sm font-medium border-b-2 {{ $activeTab === $tab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                            {{ $label }}
                            @if($tab === 'resumo' && ! $fichaComplete)
                                <span class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white">!</span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            @if($activeRental)
                <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 text-sm">
                    <span class="font-medium text-indigo-800">Locação ativa:</span>
                    <a href="{{ route('rentals.show', $activeRental) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $activeRental->codigo }}</a>
                    <span class="text-indigo-700">— {{ $activeRental->customer->nome }} ({{ $activeRental->statusEnum()->label() }})</span>
                </div>
            @endif

            @if($activeMaintenanceOrder)
                <div class="bg-orange-50 border border-orange-100 rounded-lg p-4 text-sm">
                    <span class="font-medium text-orange-800">OS ativa:</span>
                    <a href="{{ route('maintenance.show', $activeMaintenanceOrder) }}" wire:navigate class="text-orange-600 hover:underline">{{ $activeMaintenanceOrder->codigo }}</a>
                    <span class="text-orange-700">— {{ $activeMaintenanceOrder->statusEnum()->label() }}</span>
                </div>
            @endif

            @if($activeTab === 'resumo')
                @php
                    $fieldWarning = fn (string $field) => \App\Support\FichaCompleteness::hasFieldWarning($fichaWarnings, $field);
                    $warningText = fn (string $field) => collect($fichaWarnings)->firstWhere('field', $field)['message'] ?? '';
                    $canEdit = auth()->user()->can('update', $asset);
                @endphp

                @if(count($fichaWarnings) > 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-medium mb-1">Campos com alerta — clique no campo para preencher:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-amber-800">
                            @foreach($fichaWarnings as $warning)
                                <li>{{ $warning['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
                            <h3 class="font-semibold text-gray-800">Ficha do patrimônio</h3>
                            <div class="flex items-center gap-2">
                                <x-sheet-incomplete-badge :warnings="$fichaWarnings" />
                                @if($canEdit)
                                    <span class="text-xs text-gray-400">Clique em qualquer campo para editar</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-sm text-gray-600">
                            <span class="text-gray-500">Modelo:</span>
                            <strong>{{ $asset->equipmentModel->displayName() }}</strong>
                            <span class="text-gray-400">— {{ $asset->equipmentModel->category->nome }}</span>
                        </div>

                        <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Descrição do equipamento"
                                    :display="$asset->descricao"
                                    type="textarea"
                                    :editable="$canEdit"
                                    save="saveFicha"
                                    :warning="$fieldWarning('descricao')"
                                    :warning-message="$warningText('descricao')"
                                    wire:model="ficha_descricao"
                                />
                            </div>
                            <x-inline-field
                                label="Número de série"
                                :display="$asset->serie"
                                :editable="$canEdit"
                                save="saveFicha"
                                :warning="$fieldWarning('serie')"
                                :warning-message="$warningText('serie')"
                                wire:model="ficha_serie"
                            />
                            <x-inline-field
                                label="Horímetro"
                                :display="$asset->horimetro !== null ? number_format($asset->horimetro, 2, ',', '.').' h' : null"
                                type="number"
                                :editable="$canEdit"
                                save="saveFicha"
                                :warning="$fieldWarning('horimetro')"
                                :warning-message="$warningText('horimetro')"
                                wire:model="ficha_horimetro"
                            />
                            <x-inline-field
                                label="Voltagem"
                                :display="$asset->voltagem"
                                :editable="$canEdit"
                                save="saveFicha"
                                wire:model="ficha_voltagem"
                                placeholder="Ex.: 220V"
                            />
                            <x-inline-field
                                label="Localização"
                                :display="$asset->localizacao"
                                :editable="$canEdit"
                                save="saveFicha"
                                wire:model="ficha_localizacao"
                                placeholder="Ex.: Pátio A"
                            />
                            @can('updatePurchaseValue', $asset)
                                <x-inline-field
                                    label="Valor de aquisição"
                                    :display="$asset->valor_compra ? 'R$ '.number_format($asset->valor_compra, 2, ',', '.') : null"
                                    type="currency"
                                    :editable="true"
                                    save="saveFicha"
                                    wire:model="ficha_valor_compra"
                                />
                                <x-inline-field
                                    label="Data de aquisição"
                                    :display="$asset->data_compra?->format('d/m/Y')"
                                    type="date"
                                    :editable="true"
                                    save="saveFicha"
                                    wire:model="ficha_data_compra"
                                />
                            @endcan
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Observações"
                                    :display="$asset->observacoes"
                                    type="textarea"
                                    :editable="$canEdit"
                                    save="saveFicha"
                                    wire:model="ficha_observacoes"
                                />
                            </div>
                            @if($asset->motivo_bloqueio)
                                <div class="md:col-span-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                                    <span class="font-medium">Motivo do bloqueio:</span> {{ $asset->motivo_bloqueio }}
                                </div>
                            @endif
                        </div>

                        <livewire:custom-field.custom-field-panel :entity-type="'asset'" :entity-id="$asset->id" :key="'cf-asset-'.$asset->id" :inline="true" />
                    </div>

                    <div class="bg-white rounded-lg shadow p-6 text-center">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">QR Code</h3>
                        @if($hasQrImage)
                            <img src="{{ route('assets.qr-image', $asset) }}" alt="QR Code" class="mx-auto h-40 w-40 rounded border border-gray-200" />
                            <p class="mt-2 text-xs text-gray-500">Escaneie para abrir esta ficha</p>
                        @else
                            <div class="mx-auto flex h-40 w-40 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-500">
                                @if($qrStatus->value === 'pending')
                                    Gerando...
                                @elseif($qrStatus->value === 'failed')
                                    Falhou
                                @else
                                    Indisponível
                                @endif
                            </div>
                            @if($asset->qr_code_error)
                                <p class="mt-2 text-xs text-red-600">{{ $asset->qr_code_error }}</p>
                            @endif
                        @endif
                        <div class="mt-3">
                            <span @class([
                                'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                'bg-amber-100 text-amber-800' => $qrStatus->value === 'pending',
                                'bg-green-100 text-green-800' => $qrStatus->value === 'generated',
                                'bg-red-100 text-red-800' => $qrStatus->value === 'failed',
                            ])>{{ $qrStatus->label() }}</span>
                        </div>
                        @can('update', $asset)
                            <x-btn-secondary wire:click="reprocessQrCode" class="mt-4 w-full text-xs">
                                {{ $hasQrImage ? 'Regenerar QR' : 'Gerar QR' }}
                            </x-btn-secondary>
                        @endcan
                    </div>
                </div>
            @endif

            @if($activeTab === 'manutencao')
                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-800">Manutenção do patrimônio</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Abra OS aqui ou em Manutenção → Nova OS (fora da ficha).</p>
                            </div>
                            @can('create', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                                @if(! $activeMaintenanceOrder)
                                    <x-btn-primary wire:click="openCorrectiveOrder" class="text-sm inline-flex items-center">
                                        + Abrir OS corretiva
                                        <x-help-hint text="Cria ordem de serviço corretiva para este patrimônio. Também é possível abrir em Manutenção → Nova OS." class="ml-2" />
                                    </x-btn-primary>
                                @endif
                            @endcan
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <h3 class="font-semibold text-gray-800">Regras preventivas do tipo de equipamento</h3>
                            @can('manage', App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class)
                                <a href="{{ route('maintenance.preventive.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Gerenciar regras</a>
                            @endcan
                        </div>
                        @if($preventiveStatuses->isEmpty())
                            <p class="text-sm text-gray-500">Nenhuma regra preventiva cadastrada para {{ $asset->equipmentModel->displayName() }}.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($preventiveStatuses as $status)
                                    @php $rule = $status['rule']; @endphp
                                    <div @class([
                                        'rounded-lg border p-4 text-sm',
                                        'border-red-200 bg-red-50' => $status['vencida'],
                                        'border-gray-200 bg-gray-50' => ! $status['vencida'],
                                    ])>
                                        <div class="flex flex-wrap justify-between gap-2">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $rule->descricao }}</p>
                                                <p class="text-gray-600 mt-1">A cada {{ number_format($rule->interval_horas, 0, ',', '.') }} horas de uso</p>
                                                @if($status['horas_desde_ultima'] !== null)
                                                    <p class="text-gray-500 mt-1">
                                                        Horas desde última preventiva:
                                                        {{ number_format($status['horas_desde_ultima'], 1, ',', '.') }} h
                                                        @if($status['proxima_em'] !== null && ! $status['vencida'])
                                                            — próxima em {{ number_format($status['proxima_em'], 1, ',', '.') }} h
                                                        @endif
                                                    </p>
                                                @else
                                                    <p class="text-amber-600 mt-1">Horímetro não registrado — impossível calcular vencimento.</p>
                                                @endif
                                                @if($status['ultima_os'])
                                                    <p class="text-xs text-gray-400 mt-1">Última OS: {{ $status['ultima_os']->codigo }} em {{ $status['ultima_os']->completed_at?->format('d/m/Y') }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-start gap-2">
                                                @if($status['vencida'])
                                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">Vencida</span>
                                                @endif
                                                @can('create', App\Models\Domain\Maintenance\MaintenanceOrder::class)
                                                    @if($status['vencida'] && ! $activeMaintenanceOrder)
                                                        <x-btn-primary wire:click="openPreventiveOrder({{ $rule->id }})" class="text-xs inline-flex items-center">
                                                            Abrir OS preventiva
                                                            <x-help-hint text="Cria uma OS preventiva para este patrimônio com base na regra vencida. O equipamento segue para manutenção programada." class="ml-1" />
                                                        </x-btn-primary>
                                                    @endif
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="font-semibold text-gray-800">Histórico de manutenção</h3>
                            <p class="text-sm text-gray-500 mt-1">Corretivas, preventivas e demais ordens de serviço deste patrimônio.</p>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">OS</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Horímetro</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abertura</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conclusão</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($maintenanceHistory as $order)
                                    <tr class="text-sm">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('maintenance.show', $order) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $order->codigo }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $order->tipoEnum()->label() }}</td>
                                        <td class="px-4 py-3"><x-status-badge :status="$order->statusEnum()" /></td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->horimetro_servico ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->opened_at?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->completed_at?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Nenhuma manutenção registrada.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeTab === 'timeline')
                <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
                    @forelse($timeline as $event)
                        <div class="p-4 text-sm">
                            <div class="flex justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                        'bg-indigo-100 text-indigo-700' => $event['tipo'] === 'status',
                                        'bg-emerald-100 text-emerald-700' => $event['tipo'] === 'localizacao',
                                        'bg-indigo-100 text-indigo-700' => $event['tipo'] === 'locacao',
                                        'bg-orange-100 text-orange-700' => $event['tipo'] === 'manutencao',
                                    ])>{{ $event['tipo'] }}</span>
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $event['titulo'] }}</span>
                                        @if($event['detalhe'])
                                            <p class="text-gray-500 mt-1">{{ $event['detalhe'] }}</p>
                                        @endif
                                        <p class="text-xs text-gray-400 mt-1">{{ $event['usuario'] ?? 'Sistema' }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-gray-400">{{ $event['data']->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="p-4 text-gray-500 text-sm">Nenhum evento registrado.</p>
                    @endforelse
                </div>
            @endif

            @if($activeTab === 'anexos')
                <div class="space-y-4">
                    @can('manageAttachments', $asset)
                        <div class="bg-white rounded-lg shadow p-6">
                            <form wire:submit="uploadAttachment" class="flex flex-wrap items-end gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Enviar documento (PDF, foto, doc — máx. 10MB)</label>
                                    <input wire:model="attachmentFile" type="file" class="mt-1 text-sm" />
                                    @error('attachmentFile') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                                <x-btn-primary type="submit" wire:loading.attr="disabled">Enviar</x-btn-primary>
                            </form>
                        </div>
                    @endcan

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arquivo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tamanho</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enviado por</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($asset->attachments as $attachment)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">{{ $attachment->nome_original }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $attachment->humanSize() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $attachment->user?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right space-x-2">
                                            <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 text-sm hover:underline">Download</a>
                                            @can('manageAttachments', $asset)
                                                <button wire:click="deleteAttachment({{ $attachment->id }})" wire:confirm="Remover este anexo?" class="text-red-600 text-sm hover:underline">Excluir</button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 text-sm">Nenhum anexo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($showStatusModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-4">Alterar status</h3>
                <form wire:submit="changeStatus" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Novo status</label>
                        <select wire:model="new_status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Selecione...</option>
                            @foreach($allowedTransitions as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('new_status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo (obrigatório para bloqueio)</label>
                        <textarea wire:model="motivo" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="$set('showStatusModal', false)">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Confirmar</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showLocationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-4">Mover patrimônio</h3>
                <form wire:submit="moveLocation" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Localização atual</label>
                        <p class="mt-1 text-sm text-gray-600">{{ $asset->localizacao ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nova localização *</label>
                        <input wire:model="nova_localizacao" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="Ex: Pátio B, Galpão 2" />
                        @error('nova_localizacao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo (opcional)</label>
                        <textarea wire:model="motivo_movimentacao" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="$set('showLocationModal', false)">Cancelar</x-btn-secondary>
                        <x-btn-primary type="submit">Confirmar movimentação</x-btn-primary>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
