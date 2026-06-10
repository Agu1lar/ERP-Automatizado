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
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-4">Concluir inspeção</h3>
            <form wire:submit="completeInspection" class="space-y-4">
                <p class="text-sm text-gray-600">Confirma a conclusão da inspeção e finalização da locação?</p>
                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="sendToMaintenance" type="checkbox" class="rounded border-gray-300" />
                    <span>Enviar patrimônio para manutenção</span>
                </label>
                @if($sendToMaintenance)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo da manutenção *</label>
                        <textarea wire:model="motivoManutencao" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        @error('motivoManutencao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
                @error('complete') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="$set('showCompleteModal', false)">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Concluir</x-btn-primary>
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
