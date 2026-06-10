<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <a href="{{ route('fleet.categories.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar para categorias</a>
                    <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ $category->nome }}</h2>
                    <p class="text-gray-500">{{ $category->tipo_linha }} · {{ $totalAssets }} patrimônio(s) nesta categoria</p>
                </div>
                @can('update', $category)
                    <a href="{{ route('fleet.categories.index') }}" wire:navigate class="btn-secondary text-sm">Editar categoria</a>
                @endcan
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach($groupLabels as $groupKey => $groupLabel)
                    @php
                        $assets = $board[$groupKey];
                        $columnStyles = match($groupKey) {
                            'disponivel' => 'border-emerald-200 bg-emerald-50/40',
                            'locado' => 'border-indigo-200 bg-indigo-50/40',
                            'manutencao' => 'border-orange-200 bg-orange-50/40',
                            default => 'border-gray-200 bg-gray-50',
                        };
                        $badgeStyles = match($groupKey) {
                            'disponivel' => 'bg-emerald-100 text-emerald-800',
                            'locado' => 'bg-indigo-100 text-indigo-800',
                            'manutencao' => 'bg-orange-100 text-orange-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp
                    <div class="rounded-lg border {{ $columnStyles }} shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-inherit flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-gray-800">{{ $groupLabel }}</h3>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeStyles }}">{{ $assets->count() }}</span>
                        </div>
                        <div class="divide-y divide-gray-100/80 max-h-[32rem] overflow-y-auto">
                            @forelse($assets as $asset)
                                <div class="px-4 py-3 bg-white/70 hover:bg-white transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <a
                                                href="{{ route('assets.show', $asset) }}"
                                                wire:navigate
                                                data-tab-title="{{ $asset->codigo_patrimonio }}"
                                                class="font-medium text-indigo-600 hover:underline"
                                            >
                                                {{ $asset->codigo_patrimonio }}
                                            </a>
                                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $asset->equipmentModel->displayName() }}</p>
                                            @if($asset->localizacao)
                                                <p class="text-xs text-gray-400 mt-1">{{ $asset->localizacao }}</p>
                                            @endif
                                        </div>
                                        <x-status-badge :status="$asset->statusEnum()" />
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a
                                            href="{{ route('assets.show', $asset) }}"
                                            wire:navigate
                                            data-tab-title="{{ $asset->codigo_patrimonio }}"
                                            class="text-xs text-indigo-600 hover:underline"
                                        >
                                            Abrir ficha
                                        </a>
                                        <a
                                            href="{{ route('assets.print', $asset) }}"
                                            target="_blank"
                                            class="text-xs text-gray-500 hover:underline"
                                        >
                                            Imprimir
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-8 text-sm text-center text-gray-500">Nenhum patrimônio neste grupo.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
