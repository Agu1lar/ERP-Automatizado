<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <a href="{{ route('rentals.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $rental->codigo }}</h2>
                    <p class="text-gray-500">{{ $rental->asset->codigo_patrimonio }} — {{ $rental->customer->nome }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-sheet-incomplete-badge :warnings="$fichaWarnings" />
                    <a href="{{ route('rentals.pdf', $rental) }}" target="_blank" class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Baixar PDF</a>
                    <x-status-badge :status="$status" />
                    @if($status === \App\Enums\RentalStatus::Reservado)
                        @can('operate', $rental)
                            <x-btn-primary wire:click="openCheckoutModal">Registrar saída</x-btn-primary>
                        @endcan
                        @can('cancel', $rental)
                            <x-btn-secondary wire:click="openCancelModal">Cancelar reserva</x-btn-secondary>
                        @endcan
                    @elseif($status === \App\Enums\RentalStatus::Locado)
                        @can('operate', $rental)
                            <x-btn-secondary wire:click="openExtendModal">Prorrogar</x-btn-secondary>
                            <x-btn-primary wire:click="openReturnModal">Registrar retorno</x-btn-primary>
                        @endcan
                    @elseif($status === \App\Enums\RentalStatus::EmInspecao)
                        @can('operate', $rental)
                            <x-btn-primary wire:click="openCompleteModal">Concluir inspeção</x-btn-primary>
                        @endcan
                    @endif
                </div>
            </div>

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

            <div class="bg-white rounded-lg shadow p-6 space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
                    <h3 class="font-semibold text-gray-800">Ficha da locação</h3>
                    @if($canEditFicha)
                        <span class="text-xs text-gray-400">Clique em qualquer campo para editar e salvar</span>
                    @endif
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
                                    Horímetro: {{ $rental->asset->horimetro ?? '—' }} |
                                    Série: {{ $rental->asset->serie ?? '—' }}
                                </div>
                            @endif
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
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Cliente</h4>
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

                    <livewire:custom-field.custom-field-panel :entity-type="'rental'" :entity-id="$rental->id" :inline="true" :key="'cf-rental-'.$rental->id" />
                @else
                    <p class="text-sm text-gray-500">Sem permissão para editar a ficha.</p>
                @endif
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                    <h3 class="font-semibold text-gray-800 mb-2">Resumo operacional</h3>
                    <div><span class="text-gray-500">Patrimônio:</span>
                        <a href="{{ route('assets.show', $rental->asset) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $rental->asset->codigo_patrimonio }}</a>
                    </div>
                    <div><span class="text-gray-500">Equipamento:</span> {{ $rental->asset->equipmentModel->displayName() }}</div>
                    <div><span class="text-gray-500">Status do patrimônio:</span> <x-status-badge :status="$rental->asset->statusEnum()" /></div>
                    <div><span class="text-gray-500">Local da obra:</span> {{ $rental->local_obra ?? '—' }}</div>
                    <div><span class="text-gray-500">Localização patrimônio:</span> {{ $rental->asset->localizacao ?? '—' }}</div>
                    <div><span class="text-gray-500">Previsão de retorno:</span> {{ $rental->expected_return_at?->format('d/m/Y') ?? '—' }}</div>
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
        </div>
    </div>

    @include('livewire.rental.partials.rental-modals')
</div>
