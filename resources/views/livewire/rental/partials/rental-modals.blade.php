@if($showCheckoutModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Registrar saída</h3>
            <form wire:submit="checkout" class="space-y-4">
                <p class="text-sm text-gray-600">Checklist de saída — marque todos os itens:</p>
                @foreach($saidaTemplate as $key => $label)
                    <label class="flex items-start gap-2 text-sm">
                        <input wire:model="checklistItems.{{ $key }}" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
                @error('checklist') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div>
                    <label class="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea wire:model="checklistObservacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                @can('create', App\Models\Domain\Finance\ReceivableTitle::class)
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3 space-y-3 text-sm">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vencimento do título</label>
                            <input wire:model="checkout_title_vencimento" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                            @error('checkout_title_vencimento') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            <p class="text-xs text-gray-600 mt-1">O título é criado na saída. Ajuste o vencimento antes de faturar, se necessário.</p>
                        </div>
                        <label class="flex items-start gap-2">
                            <input wire:model="gerar_fatura_na_saida" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                            <span>
                                <span class="font-medium text-gray-900">Gerar fatura ao confirmar a saída</span>
                                <span class="block text-xs text-gray-600 mt-0.5">Autoriza e emite a fatura imediatamente após a saída.</span>
                            </span>
                        </label>
                    </div>
                @endcan
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showCheckoutModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Confirmar saída</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showReturnModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Registrar retorno</h3>
            <form wire:submit="registerReturn" class="space-y-4">
                <p class="text-sm text-gray-600">Checklist de retorno — marque todos os itens:</p>
                @foreach($retornoTemplate as $key => $label)
                    <label class="flex items-start gap-2 text-sm">
                        <input wire:model="checklistItems.{{ $key }}" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
                @error('checklist') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div>
                    <label class="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea wire:model="checklistObservacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showReturnModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Confirmar retorno</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showCompleteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-2">Concluir inspeção</h3>
            <p class="text-sm text-gray-600 mb-4">Escolha o resultado da vistoria. Isso encerra a ficha e define o destino do equipamento.</p>
            <form wire:submit="completeInspection" class="space-y-4">
                <div class="space-y-2">
                    <label @class(['flex items-start gap-3 rounded-lg border p-3 cursor-pointer', 'border-indigo-500 bg-indigo-50' => $inspectionOutcome === 'ok', 'border-gray-200' => $inspectionOutcome !== 'ok'])>
                        <input wire:model.live="inspectionOutcome" type="radio" value="ok" class="mt-1" />
                        <span>
                            <span class="font-medium text-gray-900">Equipamento OK</span>
                            <span class="block text-xs text-gray-600 mt-0.5">Libera o patrimônio para nova locação.</span>
                        </span>
                    </label>
                    <label @class(['flex items-start gap-3 rounded-lg border p-3 cursor-pointer', 'border-indigo-500 bg-indigo-50' => $inspectionOutcome === 'maintenance', 'border-gray-200' => $inspectionOutcome !== 'maintenance'])>
                        <input wire:model.live="inspectionOutcome" type="radio" value="maintenance" class="mt-1" />
                        <span>
                            <span class="font-medium text-gray-900">Enviar para manutenção</span>
                            <span class="block text-xs text-gray-600 mt-0.5">Abre OS de retorno de locação vinculada a esta ficha.</span>
                        </span>
                    </label>
                    <label @class(['flex items-start gap-3 rounded-lg border p-3 cursor-pointer', 'border-indigo-500 bg-indigo-50' => $inspectionOutcome === 'indenizacao', 'border-gray-200' => $inspectionOutcome !== 'indenizacao'])>
                        <input wire:model.live="inspectionOutcome" type="radio" value="indenizacao" class="mt-1" />
                        <span>
                            <span class="font-medium text-gray-900">Indenização por dano</span>
                            <span class="block text-xs text-gray-600 mt-0.5">Abre OS de indenização e lança cobrança complementar na fatura.</span>
                        </span>
                    </label>
                </div>
                @if(in_array($inspectionOutcome, ['maintenance', 'indenizacao']))
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descrição do problema / motivo *</label>
                        <textarea wire:model="motivoManutencao" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Ex.: cabo danificado, motor queimado"></textarea>
                        @error('motivoManutencao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
                @if($inspectionOutcome === 'indenizacao')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor da indenização (R$) *</label>
                        <input wire:model="os_valor_indenizacao" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="0,00" />
                        @error('os_valor_indenizacao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
                @error('complete') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showCompleteModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Encerrar ficha</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showExtendModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-4">Prorrogar locação</h3>
            <p class="text-sm text-gray-600 mb-4">Vencimento atual: <strong>{{ $rental->expected_return_at?->format('d/m/Y') }}</strong></p>
            <form wire:submit="extendRental" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nova previsão de retorno *</label>
                    <input wire:model="extend_expected_return_at" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('extend_expected_return_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Período de cobrança</label>
                    <select wire:model="extend_pricing_period" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Automático (menor valor)</option>
                        @foreach($pricingPeriodOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showExtendModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Prorrogar e recalcular</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showCancelModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-4">Cancelar reserva</h3>
            <form wire:submit="cancelReservation" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Motivo do cancelamento *</label>
                    <textarea wire:model="cancelReason" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    @error('cancelReason') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showCancelModal', false)">Voltar</x-btn-secondary>
                    <x-btn-primary type="submit" class="!bg-red-600 hover:!bg-red-700">Cancelar reserva</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showSubstituteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-2">Substituir equipamento</h3>
            <p class="text-sm text-gray-600 mb-4">
                A locação <strong>{{ $rental->codigo }}</strong> permanece a mesma. O patrimônio atual
                (<strong>{{ $rental->asset->codigo_patrimonio }}</strong>) será liberado para manutenção em campo.
            </p>
            <form wire:submit="substituteAsset" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Buscar patrimônio substituto</label>
                    <input wire:model.live.debounce.300ms="substitute_asset_search" type="search" placeholder="Código ou modelo..." class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Patrimônio substituto *</label>
                    <select wire:model="substitute_asset_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Selecione...</option>
                        @foreach($substituteAssetSuggestions as $asset)
                            <option value="{{ $asset['id'] }}">{{ $asset['label'] }} — {{ $asset['subtitle'] }}</option>
                        @endforeach
                    </select>
                    @error('substitute_asset_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Motivo (opcional)</label>
                    <textarea wire:model="substitute_motivo" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Ex.: equipamento com defeito na obra"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showSubstituteModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Confirmar substituição</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showTransferCommercialModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-2">Transferir responsabilidade comercial</h3>
            <p class="text-sm text-gray-600 mb-4">
                O faturamento desta locação passará a ser contabilizado para o usuário selecionado nos relatórios comerciais.
            </p>
            <form wire:submit="transferCommercialUser" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Novo responsável *</label>
                    <select wire:model="transfer_commercial_user_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Selecione…</option>
                        @foreach($commercialUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('transfer_commercial_user_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showTransferCommercialModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Confirmar transferência</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showOpenOsModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold mb-2">Abrir ordem de serviço</h3>
            <p class="text-sm text-gray-600 mb-4">
                A OS será vinculada à locação <strong>{{ $rental->codigo }}</strong>
                e ao patrimônio <strong>{{ $rental->asset->codigo_patrimonio }}</strong>.
                @if(in_array($status, [\App\Enums\RentalStatus::Locado, \App\Enums\RentalStatus::Reservado]))
                    <span class="block mt-1 text-amber-700">Enquanto o equipamento estiver locado, a OS será registrada sem bloquear o patrimônio.</span>
                @endif
            </p>
            <form wire:submit="createMaintenanceOrder" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo da OS *</label>
                    <select wire:model.live="os_tipo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach($maintenanceTypeOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrição do problema *</label>
                    <textarea wire:model="os_descricao" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Descreva o defeito, avaria ou serviço necessário"></textarea>
                    @error('os_descricao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                @if($os_tipo === \App\Enums\MaintenanceOrderType::Indenizacao->value)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valor da indenização (R$) *</label>
                        <input wire:model="os_valor_indenizacao" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('os_valor_indenizacao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">Será incluído na fila a faturar.</p>
                    </div>
                @endif
                @if(! in_array($status, [\App\Enums\RentalStatus::Locado, \App\Enums\RentalStatus::Reservado]))
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="os_impeditiva" type="checkbox" class="rounded border-gray-300" />
                        <span>OS impeditiva (bloqueia uso do patrimônio)</span>
                    </label>
                @endif
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showOpenOsModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Abrir OS</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif

@if($showPostFlowPrompt)
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Operação concluída</h3>
            <p class="text-sm text-gray-600 mb-5">{{ $postFlowMessage }}</p>
            <p class="text-sm text-gray-700 mb-5">Deseja permanecer na ficha <strong>{{ $rental->codigo }}</strong> ou ir para o próximo passo?</p>
            <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                <x-btn-primary type="button" wire:click="stayOnRentalFicha" class="text-sm">
                    Permanecer na ficha
                </x-btn-primary>
                <x-btn-secondary type="button" wire:click="goToPostFlowDestination" class="text-sm">
                    {{ $postFlowGoLabel }}
                </x-btn-secondary>
            </div>
        </div>
    </div>
@endif
