<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Patrimônio</th>
                <th class="px-4 py-3 text-left">Modelo</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Localização</th>
                <th class="px-4 py-3 text-left">Locação / Cliente</th>
                <th class="px-4 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($assets as $asset)
                <tr class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $asset['codigo_patrimonio'] }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $asset['model_name'] }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$asset['status']" />
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $asset['localizacao'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($asset['rental_codigo'])
                            <span class="font-medium text-indigo-700">{{ $asset['rental_codigo'] }}</span>
                            @if($asset['customer_nome'])
                                <span class="text-gray-400">·</span> {{ $asset['customer_nome'] }}
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a
                            href="{{ $asset['primary_url'] }}"
                            wire:navigate
                            data-tab-title="{{ $asset['rental_codigo'] ?? $asset['codigo_patrimonio'] }}"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                        >
                            {{ $asset['primary_label'] }}
                        </a>
                        @if($asset['secondary_url'])
                            <a href="{{ $asset['secondary_url'] }}" wire:navigate data-tab-title="{{ $asset['codigo_patrimonio'] }}" class="ml-2 text-xs text-gray-500 hover:text-indigo-600 hover:underline">
                                {{ $asset['secondary_label'] }}
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhum patrimônio nesta categoria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
