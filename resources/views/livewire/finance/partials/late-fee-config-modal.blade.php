@if($showChargeModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:keydown.escape="closeChargeModal">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="late-fee-modal-title">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 id="late-fee-modal-title" class="text-lg font-semibold text-gray-900">Multa e juros de inadimplência</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Cadastre regras e aplique encargos aos títulos em atraso</p>
                </div>
                <button type="button" wire:click="closeChargeModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Fechar">&times;</button>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Escopo da regra</label>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="rule_scope" value="global" class="text-indigo-600" />
                            Global
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="rule_scope" value="customer" class="text-indigo-600" />
                            Cliente
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="rule_scope" value="rental" class="text-indigo-600" />
                            Contrato / locação
                        </label>
                    </div>
                </div>

                @if($rule_scope === 'customer')
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700">Cliente</label>
                        <input wire:model.live.debounce.400ms="rule_customer_search" type="search" placeholder="Buscar por nome ou documento..." class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('rule_customer_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        @if(count($ruleCustomerSuggestions) > 0)
                            <ul class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                @foreach($ruleCustomerSuggestions as $suggestion)
                                    <li>
                                        <button type="button" wire:click="selectRuleCustomer({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                                            <span class="font-medium">{{ $suggestion['nome'] }}</span>
                                            <span class="text-gray-500"> — {{ $suggestion['documento'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($rule_customer_id)
                            <p class="text-sm text-emerald-700 mt-1">Cliente selecionado: <strong>{{ $rule_customer_search }}</strong></p>
                        @endif
                    </div>
                @endif

                @if($rule_scope === 'rental')
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700">Locação (contrato)</label>
                        <input wire:model.live.debounce.400ms="rule_rental_search" type="search" placeholder="Buscar pelo código da locação..." class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('rule_rental_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        @if(count($ruleRentalSuggestions) > 0)
                            <ul class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                @foreach($ruleRentalSuggestions as $suggestion)
                                    <li>
                                        <button type="button" wire:click="selectRuleRental({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50">
                                            <span class="font-medium">{{ $suggestion['codigo'] }}</span>
                                            <span class="text-gray-500"> — {{ $suggestion['customer_nome'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($rule_rental_id)
                            <p class="text-sm text-emerald-700 mt-1">Locação selecionada: <strong>{{ $rule_rental_search }}</strong></p>
                        @endif
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Multa (% sobre o valor)</label>
                        <div class="mt-1 relative">
                            <input wire:model="multa_percent" type="number" step="0.01" min="0" max="100" class="w-full rounded-md border-gray-300 shadow-sm text-sm pr-8" />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                        </div>
                        @error('multa_percent') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Juros (% ao mês)</label>
                        <div class="mt-1 relative">
                            <input wire:model="juros_mensal_percent" type="number" step="0.01" min="0" max="100" class="w-full rounded-md border-gray-300 shadow-sm text-sm pr-8" />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                        </div>
                        @error('juros_mensal_percent') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-btn-primary type="button" wire:click="saveLateFeeRule">Salvar regra</x-btn-primary>
                </div>

                <hr class="border-gray-200" />

                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Aplicar encargos em lote (período de vencimento)</h4>
                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vencimento de</label>
                            <input wire:model="batch_vencimento_from" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('batch_vencimento_from') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vencimento até</label>
                            <input wire:model="batch_vencimento_to" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('batch_vencimento_to') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" wire:model="batch_mode" value="use_saved_rules" class="mt-0.5 text-indigo-600" />
                            <span>Usar regras salvas (locação → cliente → global) para cada título</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" wire:model="batch_mode" value="use_form_rates" class="mt-0.5 text-indigo-600" />
                            <span>Aplicar os percentuais informados acima para todos os títulos do período</span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-2 justify-end">
                        <x-btn-secondary type="button" wire:click="closeChargeModal">Sair sem aplicar</x-btn-secondary>
                        <x-btn-primary type="button" wire:click="applyBatchCharges" wire:confirm="Confirma a aplicação dos encargos nos títulos em atraso do período?">Aplicar ao período</x-btn-primary>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
