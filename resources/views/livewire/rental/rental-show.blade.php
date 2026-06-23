<x-flash-message />

<div @billing-download.window="window.open($event.detail.url, '_blank')">
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <a href="{{ route('rentals.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $rental->codigo }}</h2>
                    <p class="text-gray-500">{{ $rental->asset->codigo_patrimonio }} — {{ $rental->customer->nome }}</p>

                    <div class="text-sm text-gray-600 mt-2">
                        <form wire:submit.prevent="changeRentalCompany" class="inline-flex items-center gap-2">
                            <label class="text-xs text-gray-500">Empresa:</label>
                            <select wire:model="rental_operating_company_id" class="rounded border-gray-200 text-sm">
                                @foreach($operatingCompanies as $oc)
                                    <option value="{{ $oc->id }}">{{ $oc->nome }}@if($oc->formattedCnpj()) ({{ $oc->formattedCnpj() }})@endif</option>
                                @endforeach
                            </select>
                            <button type="submit" class="ml-2 rounded bg-indigo-600 text-white px-2 py-1 text-sm">Salvar</button>
                        </form>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-sheet-incomplete-badge :warnings="$fichaWarnings" />
                    <x-status-badge :status="$status" />
                </div>
            </div>

            <x-rental-workflow-panel
                :rental="$rental"
                :status="$status"
                :steps="$workflowSteps"
                :can-open-maintenance-order="$canOpenMaintenanceOrder"
                :can-generate-receivables="$canGenerateReceivables"
            />

            @php
                $fieldWarning = fn (string $field) => \App\Support\FichaCompleteness::hasFieldWarning($fichaWarnings, $field);
                $warningText = fn (string $field) => collect($fichaWarnings)->firstWhere('field', $field)['message'] ?? '';
                $canEditFicha = auth()->user()->can('updateFicha', $rental);
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

            @php
                $pendingBillingOnRental = $rental->billingQueueEntries->filter(
                    fn ($e) => in_array($e->status, ['pendente', 'autorizado'], true)
                )->count();
                $itemsEmCampo = $rental->items->where('ativo', true)->where('devolvido', false)->count();
            @endphp

            <div class="border-b border-gray-200">
                <nav class="flex flex-wrap gap-1 -mb-px" aria-label="Abas da ficha">
                    @foreach([
                        'dados' => 'Dados',
                        'faturamento' => 'Faturamento',
                        'devolver' => 'A devolver',
                        'anexos' => 'Anexos',
                    ] as $tab => $label)
                        <button
                            type="button"
                            wire:click="$set('activeTab', '{{ $tab }}')"
                            @class([
                                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                                'border-indigo-600 text-indigo-700' => $activeTab === $tab,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeTab !== $tab,
                            ])
                        >
                            {{ $label }}
                            @if($tab === 'faturamento' && $pendingBillingOnRental > 0)
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white">{{ $pendingBillingOnRental }}</span>
                            @endif
                            @if($tab === 'devolver' && $itemsEmCampo > 0)
                                <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-500 px-1.5 text-[10px] font-bold text-white">{{ $itemsEmCampo }}</span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            @if($activeTab === 'dados')
            <div class="bg-white rounded-lg shadow p-6 space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
                    <h3 class="font-semibold text-gray-800">Ficha da locação</h3>
                    @if($canEditFicha)
                        <span class="text-xs text-gray-400">Clique em qualquer campo para editar e salvar</span>
                    @endif
                </div>

                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                    <span class="font-medium">Empresa operacional:</span>
                    {{ $rental->operatingCompany?->nome ?? 'Não informada' }}
                    @if($rental->operatingCompany?->formattedCnpj())
                        <span class="text-indigo-700">— CNPJ {{ $rental->operatingCompany->formattedCnpj() }}</span>
                    @endif
                    <span class="block text-xs text-indigo-600 mt-1">Contratos e documentos desta ficha usam os dados desta empresa.</span>
                </div>

                @if($canEditFicha)
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Equipamento</h4>
                        <p class="text-sm text-gray-600 mb-3">
                            Modelo: <strong>{{ $rental->asset->equipmentModel->displayName() }}</strong>
                            ({{ $rental->asset->equipmentModel->category->nome }})
                        </p>
                        <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                            @if($canEditAsset)
                                <div class="md:col-span-2">
                                    <x-inline-field
                                        label="Descrição do equipamento"
                                        :display="$rental->asset->descricao"
                                        type="textarea"
                                        :editable="true"
                                        save="saveRentalField('asset_descricao')"
                                        :warning="$fieldWarning('descricao')"
                                        :warning-message="$warningText('descricao')"
                                        wire:model="asset_descricao"
                                    />
                                </div>
                                @if($usesHorimetro)
                                <x-inline-field
                                    label="Horímetro atual"
                                    :display="$rental->asset->horimetro !== null ? number_format($rental->asset->horimetro, 2, ',', '.').' h' : null"
                                    type="number"
                                    :editable="true"
                                    save="saveRentalField('asset_horimetro')"
                                    :warning="$fieldWarning('horimetro')"
                                    :warning-message="$warningText('horimetro')"
                                    wire:model="asset_horimetro"
                                />
                                @endif
                                <x-inline-field
                                    label="Série"
                                    :display="$rental->asset->serie"
                                    :editable="true"
                                    save="saveRentalField('asset_serie')"
                                    :warning="$fieldWarning('serie')"
                                    :warning-message="$warningText('serie')"
                                    wire:model="asset_serie"
                                />
                            @else
                                <div class="md:col-span-2 text-sm text-gray-600">
                                    Descrição: {{ $rental->asset->descricao ?? '—' }} |
                                    @if($usesHorimetro)
                                        Horímetro: {{ $rental->asset->horimetro ?? '—' }} |
                                    @endif
                                    Série: {{ $rental->asset->serie ?? '—' }}
                                </div>
                            @endif
                            @if($usesHorimetro)
                            <x-inline-field
                                label="Horímetro saída"
                                :display="$rental->horimetro_saida !== null ? number_format($rental->horimetro_saida, 2, ',', '.').' h' : null"
                                type="number"
                                :editable="true"
                                save="saveRentalField('ficha_horimetro_saida')"
                                :warning="$fieldWarning('horimetro_saida')"
                                :warning-message="$warningText('horimetro_saida')"
                                wire:model="ficha_horimetro_saida"
                            />
                            <x-inline-field
                                label="Horímetro retorno"
                                :display="$rental->horimetro_retorno !== null ? number_format($rental->horimetro_retorno, 2, ',', '.').' h' : null"
                                type="number"
                                :editable="true"
                                save="saveRentalField('ficha_horimetro_retorno')"
                                :warning="$fieldWarning('horimetro_retorno')"
                                :warning-message="$warningText('horimetro_retorno')"
                                wire:model="ficha_horimetro_retorno"
                            />
                            @else
                                <p class="md:col-span-2 text-xs text-gray-500 px-3">Esta categoria de equipamento não utiliza horímetro.</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Responsável comercial</h4>
                        <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-indigo-700">Usuário da ficha</p>
                                    <p class="font-semibold text-indigo-900">{{ $rental->commercialUser?->name ?? 'Não informado' }}</p>
                                    <p class="text-xs text-indigo-600 mt-0.5">Definido automaticamente ao abrir a locação. Usado no faturamento por usuário.</p>
                                </div>
                                @can('transferCommercialUser', $rental)
                                    <x-btn-secondary type="button" wire:click="openTransferCommercialModal" class="text-xs">
                                        Transferir responsabilidade
                                    </x-btn-secondary>
                                @endcan
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Cliente</h4>
                        @if($rental->customer->createdByUser)
                            <p class="text-xs text-gray-500 mb-2">
                                Cadastrado por <strong>{{ $rental->customer->createdByUser->name }}</strong>
                                em {{ $rental->customer->created_at->format('d/m/Y H:i') }}
                            </p>
                        @endif
                        @if($canEditCustomer)
                            <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                                <x-inline-field
                                    label="Nome / Razão social"
                                    :display="$rental->customer->nome"
                                    :editable="true"
                                    save="saveRentalField('customer_nome')"
                                    :warning="$fieldWarning('nome')"
                                    :warning-message="$warningText('nome')"
                                    wire:model="customer_nome"
                                />
                                <x-inline-field
                                    label="CPF/CNPJ"
                                    :display="$rental->customer->formattedDocument()"
                                    :editable="true"
                                    save="saveRentalField('customer_cpf_cnpj')"
                                    :warning="$fieldWarning('cpf_cnpj')"
                                    :warning-message="$warningText('cpf_cnpj')"
                                    wire:model="customer_cpf_cnpj"
                                />
                                <x-inline-field
                                    label="Contato"
                                    :display="$rental->customer->contato"
                                    :editable="true"
                                    save="saveRentalField('customer_contato')"
                                    :warning="$fieldWarning('contato_nome')"
                                    :warning-message="$warningText('contato_nome')"
                                    wire:model="customer_contato"
                                />
                                <x-inline-field
                                    label="Telefone"
                                    :display="$rental->customer->telefone"
                                    :editable="true"
                                    save="saveRentalField('customer_telefone')"
                                    :warning="$fieldWarning('contato')"
                                    :warning-message="$warningText('contato')"
                                    wire:model="customer_telefone"
                                />
                                <x-inline-field
                                    label="E-mail"
                                    :display="$rental->customer->email"
                                    type="email"
                                    :editable="true"
                                    save="saveRentalField('customer_email')"
                                    wire:model="customer_email"
                                />
                                <div class="md:col-span-2">
                                    <x-inline-field
                                        label="Endereço"
                                        :display="$rental->customer->endereco"
                                        type="textarea"
                                        :editable="true"
                                        save="saveRentalField('customer_endereco')"
                                        :warning="$fieldWarning('endereco')"
                                        :warning-message="$warningText('endereco')"
                                        wire:model="customer_endereco"
                                    />
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-600 space-y-1">
                                <div>{{ $rental->customer->nome }} — {{ $rental->customer->formattedDocument() }}</div>
                                <div>Tel: {{ $rental->customer->telefone ?? '—' }} | E-mail: {{ $rental->customer->email ?? '—' }}</div>
                                <div>{{ $rental->customer->endereco ?? '—' }}</div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Locação</h4>
                        <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Local da obra"
                                    :display="$rental->local_obra"
                                    type="textarea"
                                    :editable="true"
                                    save="saveRentalField('ficha_local_obra')"
                                    :warning="$fieldWarning('local_obra')"
                                    :warning-message="$warningText('local_obra')"
                                    wire:model="ficha_local_obra"
                                    placeholder="Endereço ou referência da obra — na saída vira a localização do patrimônio"
                                />
                                @if($rental->statusEnum() === \App\Enums\RentalStatus::Locado && $rental->asset->localizacao)
                                    <p class="text-xs text-gray-500 mt-1 px-3">Patrimônio localizado em: <strong>{{ $rental->asset->localizacao }}</strong></p>
                                @endif
                            </div>
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Observações operacionais"
                                    :display="$rental->observacoes"
                                    type="textarea"
                                    :editable="true"
                                    save="saveRentalField('ficha_observacoes')"
                                    wire:model="ficha_observacoes"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Descrição para ficha/PDF"
                                    :display="$rental->ficha_descricao"
                                    type="textarea"
                                    :editable="true"
                                    save="saveRentalField('ficha_descricao')"
                                    wire:model="ficha_descricao"
                                />
                            </div>
                            <x-inline-field
                                label="Valor de faturamento"
                                :display="$rental->valor_faturamento ? 'R$ '.number_format($rental->valor_faturamento, 2, ',', '.') : null"
                                type="currency"
                                :editable="true"
                                save="saveRentalField('ficha_valor_faturamento')"
                                wire:model="ficha_valor_faturamento"
                                placeholder="0,00"
                            />
                            <x-inline-field
                                label="Frete de entrega"
                                :display="$rental->valor_frete_entrega ? 'R$ '.number_format($rental->valor_frete_entrega, 2, ',', '.') : null"
                                type="currency"
                                :editable="true"
                                save="saveRentalField('ficha_valor_frete_entrega')"
                                wire:model="ficha_valor_frete_entrega"
                                placeholder="0,00"
                            />
                            <x-inline-field
                                label="Frete de recolhida"
                                :display="$rental->valor_frete_recolhida ? 'R$ '.number_format($rental->valor_frete_recolhida, 2, ',', '.') : null"
                                type="currency"
                                :editable="true"
                                save="saveRentalField('ficha_valor_frete_recolhida')"
                                wire:model="ficha_valor_frete_recolhida"
                                placeholder="0,00"
                            />
                            @if($pricingBreakdown || $rental->valor_calculado)
                                <div class="md:col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                                    <p class="font-medium text-gray-800">Cálculo automático</p>
                                    @if($rental->pricing_period)
                                        <p class="text-gray-600 mt-1">Período: {{ \App\Enums\RentalPricingPeriod::from($rental->pricing_period)->label() }}</p>
                                    @endif
                                    @if($rental->billed_days)
                                        <p class="text-gray-600">Dias faturados: {{ $rental->billed_days }}</p>
                                    @endif
                                    @if($pricingBreakdown)
                                        <p class="text-gray-600">{{ $pricingBreakdown['breakdown'] }}</p>
                                        <p class="text-gray-500 text-xs mt-1">{{ $pricingBreakdown['source'] }}</p>
                                    @endif
                                    @if($rental->valor_calculado)
                                        <p class="font-medium text-gray-900 mt-1">Calculado: R$ {{ number_format($rental->valor_calculado, 2, ',', '.') }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Logística RMBH</h4>
                        <div class="grid md:grid-cols-2 gap-x-4 gap-y-1">
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Saída — quem busca"
                                    :display="\App\Enums\LogisticsDeliveryMode::tryFrom($rental->entrega_modalidade ?? 'empresa_entrega')?->label()"
                                    type="select"
                                    :editable="true"
                                    save="saveRentalField('ficha_entrega_modalidade')"
                                    wire:model="ficha_entrega_modalidade"
                                >
                                    @foreach($logisticsDeliveryModes as $mode)
                                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                    @endforeach
                                </x-inline-field>
                                @if(($rental->entrega_modalidade ?? 'empresa_entrega') === \App\Enums\LogisticsDeliveryMode::ClienteRetira->value)
                                    <p class="text-xs text-gray-500 mt-1 px-3">
                                        Cliente retira no pátio
                                        @if($rental->asset->yard)
                                            <strong>{{ $rental->asset->yard->displayLabel() }}</strong>
                                        @else
                                            — defina o pátio de origem no patrimônio
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <x-inline-field
                                label="Saída — data"
                                :display="$rental->entrega_agendada_em?->format('d/m/Y')"
                                type="date"
                                :editable="true"
                                save="saveRentalField('ficha_entrega_agendada_em')"
                                wire:model="ficha_entrega_agendada_em"
                            />
                            <x-inline-field
                                label="Saída — turno"
                                :display="$rental->entrega_turno ? (\App\Enums\LogisticsShift::tryFrom($rental->entrega_turno)?->label() ?? $rental->entrega_turno) : null"
                                type="select"
                                :editable="true"
                                save="saveRentalField('ficha_entrega_turno')"
                                wire:model="ficha_entrega_turno"
                            >
                                <option value="">—</option>
                                @foreach($logisticsShiftOptions as $shift)
                                    <option value="{{ $shift->value }}">{{ $shift->label() }}</option>
                                @endforeach
                            </x-inline-field>
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Saída — observações"
                                    :display="$rental->entrega_observacoes"
                                    type="textarea"
                                    :editable="true"
                                    save="saveRentalField('ficha_entrega_observacoes')"
                                    wire:model="ficha_entrega_observacoes"
                                    placeholder="{{ ($rental->entrega_modalidade ?? 'empresa_entrega') === \App\Enums\LogisticsDeliveryMode::ClienteRetira->value ? 'Ex.: cliente avisado, documento na portaria' : 'Ex.: portão lateral, referência de acesso' }}"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Devolução — quem traz"
                                    :display="\App\Enums\LogisticsReturnMode::tryFrom($rental->retirada_modalidade ?? 'empresa_recolhe')?->label()"
                                    type="select"
                                    :editable="true"
                                    save="saveRentalField('ficha_retirada_modalidade')"
                                    wire:model="ficha_retirada_modalidade"
                                >
                                    @foreach($logisticsReturnModes as $mode)
                                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                    @endforeach
                                </x-inline-field>
                                @if(($rental->retirada_modalidade ?? 'empresa_recolhe') === \App\Enums\LogisticsReturnMode::ClienteDevolve->value)
                                    <p class="text-xs text-gray-500 mt-1 px-3">
                                        Cliente devolve no pátio
                                        @if($rental->asset->yard)
                                            <strong>{{ $rental->asset->yard->displayLabel() }}</strong>
                                        @else
                                            — defina o pátio de origem no patrimônio
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <x-inline-field
                                label="Devolução — data"
                                :display="$rental->retirada_agendada_em?->format('d/m/Y')"
                                type="date"
                                :editable="true"
                                save="saveRentalField('ficha_retirada_agendada_em')"
                                wire:model="ficha_retirada_agendada_em"
                            />
                            <x-inline-field
                                label="Devolução — turno"
                                :display="$rental->retirada_turno ? (\App\Enums\LogisticsShift::tryFrom($rental->retirada_turno)?->label() ?? $rental->retirada_turno) : null"
                                type="select"
                                :editable="true"
                                save="saveRentalField('ficha_retirada_turno')"
                                wire:model="ficha_retirada_turno"
                            >
                                <option value="">—</option>
                                @foreach($logisticsShiftOptions as $shift)
                                    <option value="{{ $shift->value }}">{{ $shift->label() }}</option>
                                @endforeach
                            </x-inline-field>
                            <div class="md:col-span-2">
                                <x-inline-field
                                    label="Devolução — observações"
                                    :display="$rental->retirada_observacoes"
                                    type="textarea"
                                    :editable="true"
                                    save="saveRentalField('ficha_retirada_observacoes')"
                                    wire:model="ficha_retirada_observacoes"
                                    placeholder="{{ ($rental->retirada_modalidade ?? 'empresa_recolhe') === \App\Enums\LogisticsReturnMode::ClienteDevolve->value ? 'Ex.: horário de funcionamento do pátio' : 'Ex.: recolher após 14h, avisar cliente' }}"
                                />
                            </div>
                            @if($rental->entrega_agendada_em || $rental->retirada_agendada_em || (($rental->retirada_modalidade ?? '') === \App\Enums\LogisticsReturnMode::ClienteDevolve->value && $rental->expected_return_at))
                                <div class="md:col-span-2">
                                    <a href="{{ route('logistics.daily', ['data' => $rental->entrega_agendada_em?->toDateString() ?? $rental->retirada_agendada_em?->toDateString() ?? $rental->expected_return_at?->toDateString()]) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
                                        Ver na lista do dia →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <livewire:custom-field.custom-field-panel :entity-type="'rental'" :entity-id="$rental->id" :inline="true" :key="'cf-rental-'.$rental->id" />
                @else
                    <p class="text-sm text-gray-500">Sem permissão para editar a ficha.</p>
                @endif
            </div>

            @if($rental->assetSubstitutions->isNotEmpty())
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Histórico de substituições</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach($rental->assetSubstitutions as $sub)
                            <li class="rounded-lg border border-gray-100 px-4 py-3">
                                <span class="text-gray-500">{{ $sub->substituted_at->format('d/m/Y H:i') }}</span>
                                — <strong>{{ $sub->fromAsset->codigo_patrimonio }}</strong>
                                → <strong>{{ $sub->toAsset->codigo_patrimonio }}</strong>
                                @if($sub->motivo)<span class="text-gray-600"> · {{ $sub->motivo }}</span>@endif
                                @if($sub->horimetro_saida !== null || $sub->horimetro_entrada !== null)
                                    <span class="text-xs text-gray-500 block mt-0.5">
                                        Horímetro saída: {{ $sub->horimetro_saida !== null ? number_format($sub->horimetro_saida, 1, ',', '.') : '—' }}
                                        → entrada: {{ $sub->horimetro_entrada !== null ? number_format($sub->horimetro_entrada, 1, ',', '.') : '—' }}
                                    </span>
                                @endif
                                @if($sub->substitutedByUser)<span class="text-xs text-gray-400 block mt-0.5">por {{ $sub->substitutedByUser->name }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                    <h3 class="font-semibold text-gray-800 mb-2">Resumo operacional</h3>
                    <div><span class="text-gray-500">Responsável comercial:</span> {{ $rental->commercialUser?->name ?? '—' }}</div>
                    <div><span class="text-gray-500">Patrimônio:</span>
                        <a href="{{ route('assets.show', $rental->asset) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $rental->asset->codigo_patrimonio }}</a>
                    </div>
                    <div><span class="text-gray-500">Equipamento:</span> {{ $rental->asset->equipmentModel->displayName() }}</div>
                    <div><span class="text-gray-500">Status do patrimônio:</span> <x-status-badge :status="$rental->asset->statusEnum()" /></div>
                    <div><span class="text-gray-500">Local da obra:</span> {{ $rental->local_obra ?? '—' }}</div>
                    <div><span class="text-gray-500">Localização patrimônio:</span> {{ $rental->asset->localizacao ?? '—' }}</div>
                    <div><span class="text-gray-500">Previsão de retorno:</span> {{ $rental->expected_return_at?->format('d/m/Y') ?? '—' }}</div>
                    @if($canEditScheduledStart)
                        <x-inline-field
                            label="Início previsto"
                            :display="$rental->scheduled_start_at?->format('d/m/Y') ?? 'Imediato'"
                            type="date"
                            :editable="true"
                            save="saveRentalField('ficha_scheduled_start_at')"
                            wire:model="ficha_scheduled_start_at"
                        />
                        @error('ficha_scheduled_start_at') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    @elseif($rental->scheduled_start_at)
                        <div><span class="text-gray-500">Início previsto:</span> {{ $rental->scheduled_start_at->format('d/m/Y') }}</div>
                    @endif
                    @if($rental->cancel_reason)
                        <div class="text-red-600"><span class="font-medium">Motivo cancelamento:</span> {{ $rental->cancel_reason }}</div>
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                    <h3 class="font-semibold text-gray-800 mb-2">Linha do tempo</h3>
                    <div><span class="text-gray-500">Reservado:</span> {{ $rental->reserved_at->format('d/m/Y H:i') }} — {{ $rental->reservedByUser?->name ?? 'Sistema' }}</div>
                    @if($rental->checkout_at)
                        <div><span class="text-gray-500">Saída:</span> {{ $rental->checkout_at->format('d/m/Y H:i') }} — {{ $rental->checkoutByUser?->name ?? 'Sistema' }}</div>
                    @endif
                    @if($rental->returned_at)
                        <div><span class="text-gray-500">Retorno:</span> {{ $rental->returned_at->format('d/m/Y H:i') }} — {{ $rental->returnedByUser?->name ?? 'Sistema' }}</div>
                    @endif
                    @if($rental->completed_at)
                        <div><span class="text-gray-500">Concluído:</span> {{ $rental->completed_at->format('d/m/Y H:i') }} — {{ $rental->completedByUser?->name ?? 'Sistema' }}</div>
                    @endif
                    @if($rental->cancelled_at)
                        <div><span class="text-gray-500">Cancelado:</span> {{ $rental->cancelled_at->format('d/m/Y H:i') }} — {{ $rental->cancelledByUser?->name ?? 'Sistema' }}</div>
                    @endif
                </div>
            </div>

            @if($rental->checklists->isNotEmpty())
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Checklists</h3>
                    <div class="space-y-6">
                        @foreach($rental->checklists as $checklist)
                            <div class="border border-gray-100 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="font-medium text-gray-800">{{ $checklist->tipoEnum()->label() }}</span>
                                    <span class="text-xs text-gray-500">{{ $checklist->created_at->format('d/m/Y H:i') }} — {{ $checklist->user?->name ?? 'Sistema' }}</span>
                                </div>
                                @if($checklist->observacoes)
                                    <p class="text-sm text-gray-600 mb-2">{{ $checklist->observacoes }}</p>
                                @endif
                                <ul class="text-sm space-y-1">
                                    @foreach($checklist->items as $item)
                                        <li class="flex items-center gap-2">
                                            <span @class(['text-green-600' => $item->ok, 'text-red-500' => ! $item->ok])>{{ $item->ok ? '✓' : '✗' }}</span>
                                            {{ $item->descricao }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @endif

            @if($activeTab === 'faturamento')
                @include('livewire.rental.partials.rental-tab-faturamento')
            @endif

            @if($activeTab === 'devolver')
                @include('livewire.rental.partials.rental-tab-devolver')
            @endif

            @if($activeTab === 'anexos')
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold text-gray-800 mb-1">Anexos e observações</h3>
                <p class="text-sm text-gray-500 mb-4">Fotos de avaria, laudos, comprovantes ou documentos complementares desta locação.</p>
                @can('manageAttachments', $rental)
                    <form wire:submit="uploadAttachment" class="flex flex-wrap items-end gap-4 mb-4 pb-4 border-b border-gray-100">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Enviar arquivo (PDF, foto, doc — máx. 10MB)</label>
                            <input wire:model="attachmentFile" type="file" class="mt-1 text-sm" />
                            @error('attachmentFile') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <x-btn-primary type="submit" wire:loading.attr="disabled">Enviar anexo</x-btn-primary>
                    </form>
                @endcan
                <div class="space-y-2 text-sm">
                    @forelse($rental->attachments as $attachment)
                        <div class="flex justify-between items-center gap-2 py-2 border-b border-gray-50 last:border-0">
                            <div>
                                <span class="font-medium">{{ $attachment->nome_original }}</span>
                                <span class="text-gray-500 text-xs ml-2">{{ $attachment->humanSize() }} — {{ $attachment->user?->name ?? 'Sistema' }}</span>
                            </div>
                            <div class="space-x-2 shrink-0">
                                <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 hover:underline">Download</a>
                                @can('manageAttachments', $rental)
                                    <button wire:click="deleteAttachment({{ $attachment->id }})" wire:confirm="Remover este anexo?" class="text-red-600 hover:underline">Excluir</button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">Nenhum anexo nesta ficha.</p>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>

    @include('livewire.rental.partials.rental-modals')
    @include('livewire.finance.partials.billing-pay-modal')
</div>
