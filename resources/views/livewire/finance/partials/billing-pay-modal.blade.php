@if($showBillingPayModal && $billingPayTitle)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-2">Registrar pagamento</h3>
            <p class="text-sm text-gray-600 mb-4">
                {{ $billingPayTitle->codigo }} — R$ {{ number_format($billingPayTitle->valor, 2, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mb-4">Confirme manualmente quando o cliente pagar — o sistema não valida o recebimento automaticamente.</p>
            <form wire:submit="confirmBillingPayment" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data do pagamento</label>
                    <input wire:model="billing_pay_pago_em" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                    <select wire:model="billing_pay_method" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea wire:model="billing_pay_observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>
                @error('billing_pay') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2 justify-end">
                    <x-btn-secondary type="button" wire:click="cancelBillingPayment">Cancelar</x-btn-secondary>
                    <x-btn-primary type="submit">Confirmar pagamento</x-btn-primary>
                </div>
            </form>
        </div>
    </div>
@endif
