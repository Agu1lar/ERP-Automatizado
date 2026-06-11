<x-flash-message />



<div>

    <div class="py-8">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Locações</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Painel operacional e listagem completa</p>
                </div>
                @can('reserve', App\Models\Domain\Rental\Rental::class)
                    <x-btn-primary wire:click="openReserveForm" class="inline-flex items-center">
                        + Nova locação
                        <x-help-hint text="Reserve um patrimônio para um cliente: informe o código do equipamento, selecione o cliente e a data de retorno prevista. A saída efetiva é registrada depois." class="ml-2" />
                    </x-btn-primary>
                @endcan
            </div>

            <div class="border-b border-gray-200">
                <nav class="flex gap-6">
                    <button wire:click="$set('activeView', 'painel')" class="py-2 text-sm font-medium border-b-2 {{ $activeView === 'painel' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Painel locados
                        @if($overdueReturnsCount > 0)
                            <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $overdueReturnsCount }}</span>
                        @endif
                    </button>
                    <button wire:click="$set('activeView', 'lista')" class="py-2 text-sm font-medium border-b-2 {{ $activeView === 'lista' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Todas as locações
                    </button>
                </nav>
            </div>

            @if($activeView === 'painel')
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-800">Equipamentos locados e filtros</h3>
                        <div class="flex items-center gap-3">
                            <a
                                href="{{ route('rentals.panel.export', [
                                    'search' => $panelSearch,
                                    'status_scope' => $panelStatusScope,
                                    'category_id' => $panelCategoryId ?: null,
                                    'customer_id' => $panelCustomerId ?: null,
                                    'valor_min' => $panelValorMin ?: null,
                                    'valor_max' => $panelValorMax ?: null,
                                    'sort_by' => $panelSortBy,
                                    'sort_dir' => $panelSortDir,
                                    'show_customer_history' => $showCustomerHistory ? 1 : 0,
                                    'overdue_only' => $panelOverdueOnly ? 1 : 0,
                                ]) }}"
                                class="text-sm text-indigo-600 hover:underline"
                            >Exportar CSV ↓</a>
                            <span class="text-xs text-gray-400">Ordenação crescente por padrão na previsão de retorno</span>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <input wire:model.live.debounce.300ms="panelSearch" type="search" placeholder="Código, patrimônio ou cliente..." class="rounded-md border-gray-300 shadow-sm text-sm" />
                        <select wire:model.live="panelStatusScope" class="rounded-md border-gray-300 shadow-sm text-sm" @if($showCustomerHistory) disabled @endif>
                            <option value="locado">Somente locados</option>
                            <option value="ativos">Ativos (reservado + locado + inspeção)</option>
                            @foreach($statusOptions as $option)
                                <option value="{{ $option->value }}">Status: {{ $option->label() }}</option>
                            @endforeach
                            <option value="todos">Todos os status</option>
                        </select>
                        <select wire:model.live="panelCategoryId" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todas as categorias</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->nome }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="panelSortBy" class="rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach($sortOptions as $option)
                                <option value="{{ $option['value'] }}">Ordenar: {{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2 relative">
                            <input wire:model.live.debounce.300ms="panelCustomerSearch" type="text" placeholder="Filtrar por cliente (nome ou CPF/CNPJ)..." class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @if($panelCustomerId)
                                <button type="button" wire:click="clearPanelCustomer" class="absolute right-2 top-2 text-xs text-gray-500 hover:text-gray-700">Limpar</button>
                            @endif
                            @if(count($panelCustomerSuggestions) > 0)
                                <ul class="absolute z-20 mt-1 w-full border border-gray-200 rounded-md divide-y divide-gray-100 bg-white shadow-lg text-sm">
                                    @foreach($panelCustomerSuggestions as $suggestion)
                                        <li>
                                            <button type="button" wire:click="pickPanelCustomer({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50">
                                                <span class="font-medium">{{ $suggestion['nome'] }}</span>
                                                <span class="text-gray-500"> — {{ $suggestion['documento'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <input wire:model.live.debounce.300ms="panelValorMin" type="number" step="0.01" min="0" placeholder="Valor mín. (R$)" class="rounded-md border-gray-300 shadow-sm text-sm" />
                        <input wire:model.live.debounce.300ms="panelValorMax" type="number" step="0.01" min="0" placeholder="Valor máx. (R$)" class="rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.live="panelOverdueOnly" type="checkbox" class="rounded border-gray-300" />
                            Somente retornos atrasados
                            @if($overdueReturnsCount > 0)
                                <span class="text-xs text-red-600">({{ $overdueReturnsCount }})</span>
                            @endif
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 {{ $panelCustomerId ? '' : 'opacity-50' }}">
                            <input wire:model.live="showCustomerHistory" type="checkbox" class="rounded border-gray-300" @disabled(! $panelCustomerId) />
                            Ver histórico completo do cliente selecionado
                        </label>
                        <select wire:model.live="panelSortDir" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="asc">Crescente ↑</option>
                            <option value="desc">Decrescente ↓</option>
                        </select>
                    </div>

                    @if($selectedPanelCustomer && $showCustomerHistory)
                        <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4 text-sm">
                            <p class="font-medium text-indigo-900">Histórico de {{ $selectedPanelCustomer->nome }}</p>
                            <p class="text-indigo-700 mt-1">{{ $selectedPanelCustomer->formattedDocument() }}</p>
                            @if($customerHistorySummary->isNotEmpty())
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach($statusOptions as $option)
                                        @if($customerHistorySummary->has($option->value))
                                            <span class="rounded-full bg-white px-2 py-0.5 text-xs text-indigo-800 border border-indigo-100">
                                                {{ $option->label() }}: {{ $customerHistorySummary[$option->value] }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locação</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Local obra</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saída</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retorno prev.</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($panelRentals as $rental)
                                    <tr class="hover:bg-gray-50 text-sm {{ $rental->isReturnOverdue() ? 'bg-red-50' : '' }}">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('rentals.show', $rental) }}" wire:navigate data-tab-title="{{ $rental->codigo }}" class="font-medium text-indigo-600 hover:underline">{{ $rental->codigo }}</a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('assets.show', $rental->asset) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $rental->asset->codigo_patrimonio }}</a>
                                            <p class="text-xs text-gray-500">{{ $rental->asset->equipmentModel->displayName() }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $rental->asset->equipmentModel->category->nome }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('customers.show', $rental->customer) }}" wire:navigate data-tab-title="{{ $rental->customer->nome }}" class="text-indigo-600 hover:underline">{{ $rental->customer->nome }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 text-xs max-w-[12rem] truncate" title="{{ $rental->local_obra }}">{{ $rental->local_obra ?? '—' }}</td>
                                        <td class="px-4 py-3"><x-status-badge :status="$rental->statusEnum()" /></td>
                                        <td class="px-4 py-3 text-gray-500">{{ $rental->checkout_at?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 {{ $rental->isReturnOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                            {{ $rental->expected_return_at?->format('d/m/Y') ?? '—' }}
                                            @if($rental->isReturnOverdue())
                                                <span class="block text-xs">({{ $rental->daysOverdue() }} {{ $rental->daysOverdue() === 1 ? 'dia' : 'dias' }} atrasado)</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700">
                                            {{ $rental->valor_faturamento ? 'R$ '.number_format($rental->valor_faturamento, 2, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nenhuma locação encontrada com os filtros atuais.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $panelRentals->links() }}
                </div>
            @endif

            @if($activeView === 'lista')
            <div class="flex flex-wrap gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código, patrimônio ou cliente..." class="rounded-md border-gray-300 shadow-sm max-w-md" />
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>



            @if($activeView === 'lista' && $showReserveForm)

                <div class="bg-white rounded-lg shadow p-6">

                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Nova locação</h3>

                    @if($activeCompany)
                        <p class="text-sm text-indigo-700 mb-2">
                            Cadastrando em <strong>{{ $activeCompany->nome }}</strong>
                            @if($activeCompany->formattedCnpj())
                                (CNPJ {{ $activeCompany->formattedCnpj() }})
                            @endif
                            — o contrato e a ficha ficarão vinculados a esta empresa.
                        </p>
                    @endif

                    <p class="text-sm text-gray-500 mb-4">Cole ou digite o código do patrimônio — os dados da ficha serão preenchidos automaticamente.</p>



                    <form wire:submit="saveReservation" class="space-y-5 max-w-3xl">

                        <div class="space-y-2">

                            <label class="block text-sm font-medium text-gray-700">Patrimônio *</label>

                            <input

                                wire:model.live.debounce.400ms="asset_search"

                                type="text"

                                placeholder="Ex: PAT-0001 ou cole o código aqui"

                                class="w-full rounded-md border-gray-300 shadow-sm"

                                autocomplete="off"

                            />

                            @error('asset_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                            @if($assetResolveMessage)

                                <p class="text-sm text-amber-600">{{ $assetResolveMessage }}</p>

                            @endif



                            @if(count($assetSuggestions) > 0)

                                <ul class="border border-gray-200 rounded-md divide-y divide-gray-100 text-sm bg-white shadow-sm">

                                    @foreach($assetSuggestions as $suggestion)

                                        <li>

                                            <button

                                                type="button"

                                                wire:click="pickAsset({{ $suggestion['id'] }})"

                                                class="w-full text-left px-3 py-2 hover:bg-indigo-50 flex justify-between gap-2"

                                            >

                                                <span>

                                                    <span class="font-medium text-indigo-700">{{ $suggestion['codigo'] }}</span>

                                                    — {{ $suggestion['modelo'] }}

                                                </span>

                                                <span class="text-xs {{ $suggestion['disponivel'] ? 'text-emerald-600' : 'text-amber-600' }}">

                                                    {{ $suggestion['status'] }}

                                                </span>

                                            </button>

                                        </li>

                                    @endforeach

                                </ul>

                            @endif



                            @if($assetPreview)

                                <div class="mt-2 p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm space-y-2">

                                    <p class="font-semibold text-indigo-900">{{ $assetPreview['codigo'] }} — {{ $assetPreview['modelo'] }}</p>

                                    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1 text-gray-700">

                                        <div><span class="text-gray-500">Categoria:</span> {{ $assetPreview['categoria'] }}</div>

                                        <div><span class="text-gray-500">Status:</span> {{ $assetPreview['status'] }}</div>

                                        <div><span class="text-gray-500">Série:</span> {{ $assetPreview['serie'] ?? '—' }}</div>

                                        <div><span class="text-gray-500">Horímetro:</span> {{ $assetPreview['horimetro'] !== null ? number_format($assetPreview['horimetro'], 2, ',', '.').' h' : '—' }}</div>

                                        <div class="sm:col-span-2"><span class="text-gray-500">Localização:</span> {{ $assetPreview['localizacao'] ?? '—' }}</div>

                                        @if($assetPreview['descricao'])

                                            <div class="sm:col-span-2"><span class="text-gray-500">Descrição:</span> {{ $assetPreview['descricao'] }}</div>

                                        @endif

                                    </div>

                                    @if($prefillPreview)

                                        <p class="text-xs text-indigo-700 pt-2 border-t border-indigo-100">

                                            Na ficha da locação serão registrados automaticamente:

                                            horímetro de saída {{ $prefillPreview['horimetro_saida'] !== null ? number_format($prefillPreview['horimetro_saida'], 2, ',', '.').' h' : '(vazio)' }}

                                            @if($prefillPreview['ficha_descricao'])

                                                e descrição do equipamento.

                                            @endif

                                        </p>

                                    @endif

                                </div>

                            @endif

                        </div>



                        <div class="space-y-2">

                            <div class="flex flex-wrap items-center justify-between gap-2">

                                <label class="block text-sm font-medium text-gray-700">Cliente *</label>

                                @if($canCreateCustomer)

                                    <button type="button" wire:click="$toggle('showQuickCustomer')" class="text-xs text-indigo-600 hover:underline">

                                        {{ $showQuickCustomer ? 'Buscar cliente existente' : '+ Cadastrar cliente rápido' }}

                                    </button>

                                @endif

                            </div>



                            @if($showQuickCustomer && $canCreateCustomer)

                                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-3 text-sm">

                                    <p class="text-gray-600">Cadastre o cliente sem sair da locação:</p>

                                    <input wire:model="quick_customer_nome" type="text" placeholder="Nome / Razão social *" class="w-full rounded-md border-gray-300 shadow-sm" />

                                    @error('quick_customer_nome') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                                    <input wire:model="quick_customer_cpf_cnpj" type="text" placeholder="CPF ou CNPJ *" class="w-full rounded-md border-gray-300 shadow-sm" />

                                    @error('quick_customer_cpf_cnpj') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                                    <div class="grid sm:grid-cols-2 gap-3">

                                        <input wire:model="quick_customer_telefone" type="text" placeholder="Telefone" class="rounded-md border-gray-300 shadow-sm" />

                                        <input wire:model="quick_customer_email" type="email" placeholder="E-mail" class="rounded-md border-gray-300 shadow-sm" />

                                    </div>

                                    <x-btn-secondary type="button" wire:click="createQuickCustomer" class="text-xs">Salvar cliente e usar nesta locação</x-btn-secondary>

                                </div>

                            @else

                                <input

                                    wire:model.live.debounce.400ms="customer_search"

                                    type="text"

                                    placeholder="Nome ou CPF/CNPJ do cliente"

                                    class="w-full rounded-md border-gray-300 shadow-sm"

                                    autocomplete="off"

                                />

                                @error('customer_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror



                                @if(count($customerSuggestions) > 0)

                                    <ul class="border border-gray-200 rounded-md divide-y divide-gray-100 text-sm bg-white shadow-sm">

                                        @foreach($customerSuggestions as $suggestion)

                                            <li>

                                                <button

                                                    type="button"

                                                    wire:click="pickCustomer({{ $suggestion['id'] }})"

                                                    class="w-full text-left px-3 py-2 hover:bg-indigo-50"

                                                >

                                                    <span class="font-medium text-indigo-700">{{ $suggestion['nome'] }}</span>

                                                    <span class="text-gray-500"> — {{ $suggestion['documento'] }}</span>

                                                    @if($suggestion['telefone'])

                                                        <span class="text-gray-400 text-xs"> · {{ $suggestion['telefone'] }}</span>

                                                    @endif

                                                </button>

                                            </li>

                                        @endforeach

                                    </ul>

                                @endif



                                @if($customer_id)

                                    <p class="text-sm text-emerald-700">Cliente selecionado: <strong>{{ $customer_search }}</strong></p>

                                @endif

                            @endif

                        </div>



                        <div>
                            <label class="block text-sm font-medium text-gray-700">Local da obra</label>
                            <textarea wire:model="local_obra" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="Endereço da obra — preenchido automaticamente com o endereço do cliente, se houver"></textarea>
                            @error('local_obra') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            <p class="text-xs text-gray-500 mt-1">Na saída do equipamento, este local será registrado como localização do patrimônio.</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Previsão de retorno</label>
                                <input wire:model.live="expected_return_at" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                                @error('expected_return_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Período de cobrança</label>
                                <select wire:model.live="pricing_period" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Automático (menor valor)</option>
                                    @foreach($pricingPeriodOptions as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Observações</label>
                                <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="Opcional"></textarea>
                            </div>
                        </div>

                        @if($priceEstimate)
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                <p class="font-medium">Estimativa de faturamento</p>
                                <p class="mt-1">{{ $priceEstimate['breakdown'] }} = <strong>R$ {{ number_format($priceEstimate['valor_calculado'], 2, ',', '.') }}</strong></p>
                                <p class="text-xs text-emerald-700 mt-1">{{ $priceEstimate['billed_days'] }} dias · {{ $priceEstimate['source'] }}</p>
                            </div>
                        @elseif($asset_id && filled($expected_return_at))
                            <p class="text-sm text-amber-700">Sem tabela de preços para este equipamento — o valor deverá ser informado manualmente na ficha.</p>
                        @endif



                        <div class="flex gap-2">

                            <x-btn-primary type="submit">Criar locação</x-btn-primary>

                            <x-btn-secondary type="button" wire:click="cancelReserve">Cancelar</x-btn-secondary>

                        </div>

                    </form>

                </div>

            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Previsão retorno</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reservado em</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        @forelse($rentals as $rental)

                            <tr class="hover:bg-gray-50">

                                <td class="px-4 py-3 text-sm">

                                    <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $rental->codigo }}</a>

                                </td>

                                <td class="px-4 py-3 text-sm text-gray-700">{{ $rental->asset->codigo_patrimonio }}</td>

                                <td class="px-4 py-3 text-sm text-gray-700">{{ $rental->customer->nome }}</td>

                                <td class="px-4 py-3 text-sm"><x-status-badge :status="$rental->statusEnum()" /></td>

                                <td class="px-4 py-3 text-sm text-gray-500">{{ $rental->expected_return_at?->format('d/m/Y') ?? '—' }}</td>

                                <td class="px-4 py-3 text-sm text-gray-500">{{ $rental->reserved_at->format('d/m/Y H:i') }}</td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">Nenhuma locação encontrada.</td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>



            {{ $rentals->links() }}
            @endif

        </div>

    </div>

</div>


