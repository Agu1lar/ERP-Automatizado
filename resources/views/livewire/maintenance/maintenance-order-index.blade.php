<x-flash-message />



<div>

    <div class="py-8">

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap justify-between items-center gap-4">

                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Ordens de Serviço</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Painel operacional e listagem completa</p>
                </div>

                <div class="flex gap-2">

                    @can('viewAny', App\Models\Domain\Maintenance\PartCatalogItem::class)

                        <a href="{{ route('maintenance.parts.index') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Catálogo de peças</a>
                        @can('manage', App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class)
                            <a href="{{ route('maintenance.preventive.index') }}" wire:navigate class="btn-secondary text-sm inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Preventiva — regras</a>
                        @endcan

                    @endcan

                    @can('create', App\Models\Domain\Maintenance\MaintenanceOrder::class)

                        <x-btn-primary wire:click="openForm">+ Nova OS</x-btn-primary>

                    @endcan

                </div>

            </div>



            <div class="border-b border-gray-200">
                <nav class="flex gap-6">
                    <button wire:click="$set('activeView', 'painel')" class="py-2 text-sm font-medium border-b-2 {{ $activeView === 'painel' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Painel operacional
                        @if($overdueOrdersCount > 0)
                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $overdueOrdersCount }}</span>
                        @endif
                    </button>
                    <button wire:click="$set('activeView', 'lista')" class="py-2 text-sm font-medium border-b-2 {{ $activeView === 'lista' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Todas as OS
                    </button>
                </nav>
            </div>

            @if($activeView === 'painel')
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                        <input wire:model.live.debounce.300ms="panelSearch" type="search" placeholder="Código, patrimônio..." class="rounded-md border-gray-300 shadow-sm text-sm lg:col-span-2" />
                        <select wire:model.live="panelCategoryId" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todas categorias</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->nome }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="panelAssignedTo" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todos técnicos</option>
                            @foreach($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="panelPrioridade" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todas prioridades</option>
                            @foreach($priorityOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-4 items-center">
                        <select wire:model.live="panelTipo" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todos os tipos</option>
                            @foreach($typeOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input wire:model.live="panelOverdueOnly" type="checkbox" class="rounded border-gray-300" />
                            Somente atrasadas
                            @if($overdueOrdersCount > 0)
                                <span class="text-xs text-amber-700">({{ $overdueOrdersCount }})</span>
                            @endif
                        </label>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-4">
                        @php
                            $columnMeta = [
                                'aberta' => ['label' => 'Abertas', 'color' => 'blue'],
                                'em_execucao' => ['label' => 'Em execução', 'color' => 'orange'],
                                'aguardando_peca' => ['label' => 'Aguardando peça', 'color' => 'amber'],
                                'atrasadas' => ['label' => 'Atrasadas', 'color' => 'red'],
                            ];
                        @endphp
                        @foreach($columnMeta as $key => $meta)
                            <div class="rounded-lg border border-gray-200 bg-gray-50/50">
                                <div class="border-b border-gray-200 px-3 py-2">
                                    <h4 class="text-sm font-semibold text-gray-800">{{ $meta['label'] }}</h4>
                                    <span class="text-xs text-gray-500">{{ $boardColumns[$key]->count() }} OS</span>
                                </div>
                                <div class="max-h-[32rem] overflow-y-auto p-2 space-y-2">
                                    @forelse($boardColumns[$key] as $order)
                                        @php
                                            $isOverdue = $order->expected_completion_at && $order->expected_completion_at->lt(now()->startOfDay());
                                        @endphp
                                        <a href="{{ route('maintenance.show', $order) }}" wire:navigate data-tab-title="{{ $order->codigo }}" class="block rounded-md border {{ $isOverdue ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }} p-3 text-sm hover:border-indigo-300 transition">
                                            <p class="font-medium text-indigo-700">{{ $order->codigo }}</p>
                                            <p class="text-gray-600 mt-0.5">{{ $order->asset->codigo_patrimonio }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ $order->asset->equipmentModel->displayName() }}</p>
                                            <div class="mt-2 flex flex-wrap gap-1 items-center text-xs text-gray-500">
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5">{{ $order->prioridadeEnum()->label() }}</span>
                                                <span>{{ $order->tipoEnum()->label() }}</span>
                                            </div>
                                            @if($order->expected_completion_at)
                                                <p class="text-xs mt-1 {{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                                    Previsão: {{ $order->expected_completion_at->format('d/m/Y') }}
                                                </p>
                                            @endif
                                            @if($order->assignedToUser)
                                                <p class="text-xs text-gray-400 mt-1">{{ $order->assignedToUser->name }}</p>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="text-xs text-gray-400 text-center py-4">Nenhuma OS</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($activeView === 'lista')
            <div class="flex flex-wrap gap-3">

                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar código ou patrimônio..." class="rounded-md border-gray-300 shadow-sm max-w-md" />

                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">

                    <option value="">Todos os status</option>

                    @foreach($statusOptions as $option)

                        <option value="{{ $option->value }}">{{ $option->label() }}</option>

                    @endforeach

                </select>

            </div>
            @endif

            @if($activeView === 'lista' && $showForm)

                <div class="bg-white rounded-lg shadow p-6">

                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Nova ordem de serviço</h3>

                    <p class="text-sm text-gray-500 mb-4">Cole ou digite o código do patrimônio — cliente e dados do equipamento serão identificados automaticamente.</p>



                    <form wire:submit="save" class="space-y-4 max-w-3xl">

                        <div class="space-y-2">

                            <label class="block text-sm font-medium text-gray-700">Patrimônio *</label>

                            <input

                                wire:model.live.debounce.400ms="asset_search"

                                type="text"

                                placeholder="Ex: PAT-0001"

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

                                            <button type="button" wire:click="pickAsset({{ $suggestion['id'] }})" class="w-full text-left px-3 py-2 hover:bg-indigo-50 flex justify-between gap-2">

                                                <span><span class="font-medium text-indigo-700">{{ $suggestion['codigo'] }}</span> — {{ $suggestion['modelo'] }}</span>

                                                <span class="text-xs {{ $suggestion['has_open_os'] ? 'text-red-600' : 'text-gray-500' }}">{{ $suggestion['status'] }}</span>

                                            </button>

                                        </li>

                                    @endforeach

                                </ul>

                            @endif



                            @if($assetPreview)

                                <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm space-y-2">

                                    <p class="font-semibold text-indigo-900">{{ $assetPreview['codigo'] }} — {{ $assetPreview['modelo'] }}</p>

                                    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1 text-gray-700">

                                        <div><span class="text-gray-500">Marca:</span> {{ $assetPreview['marca'] }}</div>

                                        <div><span class="text-gray-500">Voltagem:</span> {{ $assetPreview['voltagem'] ?? '—' }}</div>

                                        <div><span class="text-gray-500">Horímetro:</span> {{ $assetPreview['horimetro'] !== null ? number_format($assetPreview['horimetro'], 2, ',', '.').' h' : '—' }}</div>

                                        <div><span class="text-gray-500">Status:</span> {{ $assetPreview['status'] }}</div>

                                        @if($assetPreview['cliente'])

                                            <div class="sm:col-span-2"><span class="text-gray-500">Cliente (locação):</span> {{ $assetPreview['cliente'] }} @if($assetPreview['locacao'])<span class="text-gray-400">({{ $assetPreview['locacao'] }})</span>@endif</div>

                                        @endif

                                    </div>

                                    @if(count($assetPreview['recent_parts']) > 0)

                                        <p class="text-xs text-indigo-700 pt-2 border-t border-indigo-100">Peças usadas recentemente: {{ implode(', ', $assetPreview['recent_parts']) }}</p>

                                    @endif

                                </div>

                            @endif

                        </div>



                        <div class="grid md:grid-cols-2 gap-4">

                            <div>

                                <label class="block text-sm font-medium text-gray-700">Tipo</label>

                                <select wire:model="tipo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">

                                    @foreach($typeOptions as $option)

                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>

                                    @endforeach

                                </select>

                            </div>

                            <div>

                                <label class="block text-sm font-medium text-gray-700">Prioridade</label>

                                <select wire:model="prioridade" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">

                                    @foreach($priorityOptions as $option)

                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>

                                    @endforeach

                                </select>

                            </div>

                        </div>

                        <div>

                            <label class="block text-sm font-medium text-gray-700">Descrição do problema *</label>

                            <textarea wire:model="descricao_problema" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>

                            @error('descricao_problema') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                        </div>

                        <div class="grid md:grid-cols-2 gap-4">

                            <div>

                                <label class="block text-sm font-medium text-gray-700">Previsão de conclusão</label>

                                <input wire:model="expected_completion_at" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />

                            </div>

                            <div>

                                <label class="block text-sm font-medium text-gray-700">Responsável</label>

                                <select wire:model="assigned_to" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">

                                    <option value="">Não atribuído</option>

                                    @foreach($technicians as $tech)

                                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>

                                    @endforeach

                                </select>

                            </div>

                        </div>

                        <label class="flex items-center gap-2 text-sm">

                            <input wire:model="impeditiva" type="checkbox" class="rounded border-gray-300" />

                            <span>OS impeditiva (bloqueia liberação do patrimônio)</span>

                        </label>

                        <div>

                            <label class="block text-sm font-medium text-gray-700">Observações</label>

                            <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>

                        </div>

                        <div class="flex gap-2">

                            <x-btn-primary type="submit">Abrir OS</x-btn-primary>

                            <x-btn-secondary type="button" wire:click="cancelForm">Cancelar</x-btn-secondary>

                        </div>

                    </form>

                </div>

            @endif



            @if($activeView === 'lista')
            <div class="bg-white rounded-lg shadow overflow-hidden">

                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Responsável</th>

                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Previsão</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        @forelse($orders as $order)

                            <tr class="hover:bg-gray-50">

                                <td class="px-4 py-3 text-sm">

                                    <a href="{{ route('maintenance.show', $order) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $order->codigo }}</a>

                                </td>

                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->asset->codigo_patrimonio }}</td>

                                <td class="px-4 py-3 text-sm text-gray-500">{{ $order->tipoEnum()->label() }}</td>

                                <td class="px-4 py-3 text-sm"><x-status-badge :status="$order->statusEnum()" /></td>

                                <td class="px-4 py-3 text-sm text-gray-500">{{ $order->assignedToUser?->name ?? '—' }}</td>

                                <td class="px-4 py-3 text-sm text-gray-500">{{ $order->expected_completion_at?->format('d/m/Y') ?? '—' }}</td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">Nenhuma OS encontrada.</td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>



            {{ $orders->links() }}
            @endif

        </div>

    </div>

</div>


