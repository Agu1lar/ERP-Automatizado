<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Busca global</h2>
                @if(trim($q) !== '')
                    <p class="text-gray-500 mt-1">Resultados para <strong class="text-gray-700">"{{ $q }}"</strong></p>
                @else
                    <p class="text-gray-500 mt-1">Digite uma categoria (ex.: marteletes), código de patrimônio, número de contrato (ex.: LOC-000123) ou nome de cliente.</p>
                @endif
            </div>

            @if(trim($q) === '')
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
                    Use a barra de busca no topo para pesquisar equipamentos por categoria, patrimônio, contrato ou cliente.
                </div>
            @elseif(! $hasResults)
                <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
                    <p class="text-gray-600">Nenhum resultado encontrado para "{{ $q }}".</p>
                    <p class="text-sm text-gray-400 mt-2">Tente o nome da categoria no plural (marteletes, betoneiras), o código do patrimônio ou o número do contrato (LOC-000123 ou só 123).</p>
                </div>
            @else
                @foreach($results['categories'] as $category)
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-4 py-4 border-b border-gray-100 bg-violet-50/60 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $category['nome'] }}</h3>
                                <p class="text-sm text-gray-500">{{ $category['total'] }} patrimônio(s) nesta categoria</p>
                            </div>
                            <a href="{{ route('fleet.categories.show', $category['id']) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">
                                Ver painel da categoria
                            </a>
                        </div>

                        @include('livewire.layout.partials.global-search-asset-table', ['assets' => $category['assets']])
                    </div>
                @endforeach

                @if($results['assets']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-4 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Patrimônios relacionados</h3>
                            <p class="text-sm text-gray-500">{{ $results['assets']->count() }} resultado(s)</p>
                        </div>
                        @include('livewire.layout.partials.global-search-asset-table', ['assets' => $results['assets']])
                    </div>
                @endif

                @if($results['rentals']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-4 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Contratos / locações</h3>
                            <p class="text-sm text-gray-500">{{ $results['rentals']->count() }} resultado(s)</p>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Contrato</th>
                                    <th class="px-4 py-3 text-left">Cliente</th>
                                    <th class="px-4 py-3 text-left">Patrimônio</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($results['rentals'] as $rental)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $rental['codigo'] }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $rental['customer'] }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $rental['asset_codigo'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $rental['status_label'] }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ $rental['url'] }}" wire:navigate class="text-indigo-600 hover:underline">Abrir ficha</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($results['customers']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-4 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Clientes</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Nome</th>
                                    <th class="px-4 py-3 text-left">Documento</th>
                                    <th class="px-4 py-3 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($results['customers'] as $customer)
                                    <tr @class(['bg-red-50/40' => $customer['blocked'], 'bg-amber-50/40' => ! $customer['blocked'] && ($customer['has_overdue'] ?? false)])>
                                        <td class="px-4 py-3">
                                            <x-customer-blocked-name
                                                :name="$customer['nome']"
                                                :blocked="$customer['blocked']"
                                                :reason="$customer['block_reason']"
                                                :href="$customer['url']"
                                            />
                                            @if(! $customer['blocked'] && ($customer['has_overdue'] ?? false))
                                                <p class="text-xs text-amber-700 mt-0.5">
                                                    Títulos em atraso (R$ {{ number_format($customer['overdue_balance'], 2, ',', '.') }}) — não bloqueado
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $customer['document'] }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ $customer['url'] }}" wire:navigate class="text-indigo-600 hover:underline">Abrir ficha do cliente</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
