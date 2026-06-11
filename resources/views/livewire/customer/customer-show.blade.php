<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Clientes</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">
                        <x-customer-blocked-name
                            :name="$customer->nome"
                            :blocked="$customer->isBlockedForDisplay()"
                            :reason="$customer->rentalBlockReason()"
                        />
                    </h2>
                    <p class="text-sm text-gray-500">{{ $customer->formattedDocument() }} · {{ $customer->ativo ? 'Ativo' : 'Inativo' }}</p>
                    @if($customer->isManuallyBlocked() && $customer->blockedByUser)
                        <p class="text-xs text-red-600 mt-1">
                            Bloqueado em {{ $customer->bloqueado_at?->format('d/m/Y H:i') }} por {{ $customer->blockedByUser->name }}
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-right">
                        <p class="text-xs uppercase tracking-wide text-emerald-700">Faturamento total</p>
                        <p class="text-xl font-bold text-emerald-800">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
                    </div>
                    @if($canViewFinance)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-right">
                            <p class="text-xs uppercase tracking-wide text-gray-600">Em aberto</p>
                            <p class="text-lg font-bold text-gray-900">R$ {{ number_format($openBalance, 2, ',', '.') }}</p>
                        </div>
                        @if($overdueBalance > 0)
                            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-right">
                                <p class="text-xs uppercase tracking-wide text-red-700">Em atraso</p>
                                <p class="text-lg font-bold text-red-800">R$ {{ number_format($overdueBalance, 2, ',', '.') }}</p>
                                <a href="{{ route('finance.delinquency') }}" wire:navigate class="text-xs text-red-700 hover:underline">Ver inadimplência</a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            @if($historySummary->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($statusOptions as $option)
                        @if($historySummary->has($option->value))
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">
                                {{ $option->label() }}: {{ $historySummary[$option->value] }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white rounded-lg shadow p-6 space-y-1">
                    <h3 class="font-semibold text-gray-800 mb-3">Dados do cliente</h3>
                    @if($customer->createdByUser)
                        <p class="text-xs text-gray-500 mb-3 pb-3 border-b border-gray-100">
                            Cadastrado por <strong>{{ $customer->createdByUser->name }}</strong>
                            em {{ $customer->created_at->format('d/m/Y H:i') }}
                        </p>
                    @endif
                    <x-inline-field label="Nome" :display="$customer->nome" :editable="$canEdit" save="saveField('nome')" wire:model="nome" />
                    <x-inline-field label="CPF/CNPJ" :display="$customer->formattedDocument()" :editable="$canEdit" save="saveField('cpf_cnpj')" wire:model="cpf_cnpj" />
                    <x-inline-field label="Contato" :display="$customer->contato" :editable="$canEdit" save="saveField('contato')" wire:model="contato" />
                    <x-inline-field label="Telefone" :display="$customer->telefone" :editable="$canEdit" save="saveField('telefone')" wire:model="telefone" />
                    <x-inline-field label="E-mail" :display="$customer->email" type="email" :editable="$canEdit" save="saveField('email')" wire:model="email" />
                    <x-inline-field label="Endereço" :display="$customer->endereco" type="textarea" :editable="$canEdit" save="saveField('endereco')" wire:model="endereco" />
                    <x-inline-field label="Status" :display="$customer->ativo ? 'Ativo' : 'Inativo'" type="select" :editable="$canEdit" save="saveField('ativo')" wire:model="ativo">
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </x-inline-field>
                        @if($canViewFinance)
                        <x-inline-field label="Limite de crédito (R$)" :display="$customer->limite_credito ? 'R$ '.number_format($customer->limite_credito, 2, ',', '.') : 'Sem limite'" type="number" :editable="$canEdit" save="saveField('limite_credito')" wire:model="limite_credito" placeholder="Opcional" />
                        @if($overdueBalance > 0)
                            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-3 py-2 my-2">
                                Cliente com <strong>R$ {{ number_format($overdueBalance, 2, ',', '.') }}</strong> em títulos atrasados.
                                Isso <strong>não bloqueia</strong> locação automaticamente — use o bloqueio manual abaixo se o comercial decidir restringir.
                            </p>
                        @endif
                    @endif
                    <x-inline-field label="Cliente bloqueado (decisão comercial)" :display="$customer->bloqueado ? 'Sim' : 'Não'" type="select" :editable="$canEdit" save="saveField('bloqueado')" wire:model="bloqueado">
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </x-inline-field>
                    @if($customer->bloqueado || $bloqueado === '1')
                        <x-inline-field
                            label="Justificativa do bloqueio"
                            :display="$customer->motivo_bloqueio"
                            type="textarea"
                            :editable="$canEdit"
                            save="saveField('motivo_bloqueio')"
                            wire:model="motivo_bloqueio"
                        />
                    @endif
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Locações ativas ({{ $activeRentals->count() }})</h3>
                    @forelse($activeRentals as $rental)
                        <div class="py-3 border-b border-gray-100 last:border-0 text-sm {{ $rental->isReturnOverdue() ? 'bg-red-50 -mx-2 px-2 rounded' : '' }}">
                            <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">{{ $rental->codigo }}</a>
                            <p class="text-gray-600">{{ $rental->asset->codigo_patrimonio }} — {{ $rental->asset->equipmentModel->displayName() }}</p>
                            <div class="flex flex-wrap gap-2 mt-1 items-center">
                                <x-status-badge :status="$rental->statusEnum()" />
                                @if($rental->expected_return_at)
                                    <span class="text-xs {{ $rental->isReturnOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                        Retorno: {{ $rental->expected_return_at->format('d/m/Y') }}
                                        @if($rental->isReturnOverdue())
                                            ({{ $rental->daysOverdue() }} {{ $rental->daysOverdue() === 1 ? 'dia' : 'dias' }} atrasado)
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhuma locação ativa no momento.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Histórico de locações</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saída</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conclusão</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @forelse($rentalHistory as $rental)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $rental->codigo }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $rental->asset->codigo_patrimonio }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$rental->statusEnum()" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $rental->checkout_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $rental->completed_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ $rental->valor_faturamento ? 'R$ '.number_format($rental->valor_faturamento, 2, ',', '.') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhuma locação registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $rentalHistory->links() }}</div>
            </div>

            @if($maintenanceOrders->isNotEmpty())
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Ordens de serviço recentes</h3>
                    <div class="space-y-3">
                        @foreach($maintenanceOrders as $order)
                            <div class="text-sm border-b border-gray-100 pb-3 last:border-0">
                                <a href="{{ route('maintenance.show', $order) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">{{ $order->codigo }}</a>
                                <p class="text-gray-600">{{ $order->asset->codigo_patrimonio }} — {{ $order->descricao_problema }}</p>
                                <x-status-badge :status="$order->statusEnum()" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
