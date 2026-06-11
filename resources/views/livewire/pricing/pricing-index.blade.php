<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Tabela de preços</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Defina diária, semanal e mensal por categoria — vale para todos os patrimônios daquela categoria (ex.: todas as betoneiras).
                        Preços por modelo específico têm prioridade.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('create', App\Models\Domain\Fleet\EquipmentPricing::class)
                        @if($viewMode === 'categories')
                            <x-btn-primary wire:click="saveAllCategoryPrices">Salvar todas as categorias</x-btn-primary>
                        @endif
                        <x-btn-secondary wire:click="create">+ Preço por modelo</x-btn-secondary>
                    @endcan
                </div>
            </div>

            <div class="flex gap-1 border-b border-gray-200">
                <button
                    type="button"
                    wire:click="setViewMode('categories')"
                    @class([
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                        'border-indigo-600 text-indigo-700' => $viewMode === 'categories',
                        'border-transparent text-gray-500 hover:text-gray-700' => $viewMode !== 'categories',
                    ])
                >
                    Por categoria
                </button>
                <button
                    type="button"
                    wire:click="setViewMode('advanced')"
                    @class([
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                        'border-indigo-600 text-indigo-700' => $viewMode === 'advanced',
                        'border-transparent text-gray-500 hover:text-gray-700' => $viewMode !== 'advanced',
                    ])
                >
                    Modelos e exceções
                </button>
            </div>

            @if($viewMode === 'categories')
                <div class="flex flex-wrap gap-3 items-center">
                    <input
                        wire:model.live.debounce.300ms="categorySearch"
                        type="search"
                        placeholder="Filtrar categoria (ex.: Betoneira)..."
                        class="rounded-md border-gray-300 shadow-sm max-w-md text-sm"
                    />
                    <p class="text-xs text-gray-500">
                        Categorias novas aparecem aqui automaticamente. Deixe em branco para remover o preço daquele período.
                    </p>
                </div>

                @error('categoryGrid')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[10rem]">Categoria</th>
                                @foreach($periodOptions as $option)
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase min-w-[7rem]">{{ $option->label() }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                                @can('create', App\Models\Domain\Fleet\EquipmentPricing::class)
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            @forelse($categoryRows as $row)
                                @php($category = $row['category'])
                                <tr class="hover:bg-gray-50" wire:key="category-price-{{ $category->id }}">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $category->nome }}</div>
                                        @unless($category->ativo)
                                            <span class="text-xs text-amber-700">Categoria inativa</span>
                                        @endunless
                                    </td>
                                    @foreach($periodOptions as $option)
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-gray-400 text-xs">R$</span>
                                                <input
                                                    wire:model="categoryGrid.{{ $category->id }}.{{ $option->value }}"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="—"
                                                    @cannot('create', App\Models\Domain\Fleet\EquipmentPricing::class) disabled @endcannot
                                                    class="w-28 rounded-md border-gray-300 shadow-sm text-sm text-right"
                                                />
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if($row['model_override_count'] > 0)
                                            {{ $row['model_override_count'] }} modelo(s) com preço próprio
                                        @else
                                            Todos os patrimônios
                                        @endif
                                    </td>
                                    @can('create', App\Models\Domain\Fleet\EquipmentPricing::class)
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                wire:click="saveCategoryRow({{ $category->id }})"
                                                class="text-indigo-600 hover:underline text-sm"
                                            >
                                                Salvar
                                            </button>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        Nenhuma categoria cadastrada. Crie categorias em Frota → Categorias.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-wrap gap-3">
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar modelo ou categoria..." class="rounded-md border-gray-300 shadow-sm max-w-md text-sm" />
                    <select wire:model.live="targetFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Todos os alvos</option>
                        <option value="model">Por modelo</option>
                        <option value="category">Por categoria</option>
                    </select>
                </div>

                @if($showForm)
                    <div class="bg-white rounded-lg shadow p-6">
                        <form wire:submit="save" class="space-y-4 max-w-2xl">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Aplicar preço a</label>
                                <select wire:model.live="target_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="model">Modelo específico (ex.: Bosch GBH 2-24)</option>
                                    <option value="category">Categoria inteira (ex.: Marteletes)</option>
                                </select>
                            </div>

                            @if($target_type === 'model')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Modelo *</label>
                                    <select wire:model="equipment_model_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">Selecione...</option>
                                        @foreach($models as $model)
                                            <option value="{{ $model->id }}">{{ $model->displayName() }} ({{ $model->category->nome }})</option>
                                        @endforeach
                                    </select>
                                    @error('equipment_model_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Categoria *</label>
                                    <select wire:model="equipment_category_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">Selecione...</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->nome }}</option>
                                        @endforeach
                                    </select>
                                    @error('equipment_category_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Período *</label>
                                    <select wire:model="periodo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        @foreach($periodOptions as $option)
                                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Valor (R$) *</label>
                                    <input wire:model="valor" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @error('valor') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                                Ativo
                            </label>

                            <div class="flex gap-2">
                                <x-btn-primary type="submit">Salvar</x-btn-primary>
                                <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alvo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            @forelse($pricings as $pricing)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $pricing->targetLabel() }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $pricing->equipment_model_id ? 'Modelo' : 'Categoria' }}</td>
                                    <td class="px-4 py-3">{{ $pricing->periodEnum()->label() }}</td>
                                    <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($pricing->valor, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3">{{ $pricing->ativo ? 'Ativo' : 'Inativo' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @can('update', $pricing)
                                            <button wire:click="edit({{ $pricing->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhum preço por modelo cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $pricings->links() }}
            @endif
        </div>
    </div>
</div>
