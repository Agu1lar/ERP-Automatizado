@php
    use App\Enums\GeographicRegion;

    $regionBadgeClass = fn (string $region) => match ($region) {
        GeographicRegion::Bh->value => 'bg-blue-100 text-blue-800',
        GeographicRegion::Rmbh->value => 'bg-indigo-100 text-indigo-800',
        GeographicRegion::Interior->value => 'bg-amber-100 text-amber-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

@assets
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endassets

<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                        Mapa de obras ativas
                        <x-help-hint text="Locações em campo (status Locado). Com geocoding habilitado, o pin usa coordenadas do endereço (Nominatim/Google + cache). Sem geocode, cai no centro da cidade ou região." />
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $totalOnSite }} equipamento(s) em obra agora</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('logistics.daily') }}" wire:navigate class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 hover:bg-gray-50">
                        Lista do dia
                    </a>
                    <select wire:model.live="regionFilter" class="rounded-md border-gray-300 text-sm shadow-sm" title="Filtrar por região da obra">
                        <option value="">Todas as regiões</option>
                        @foreach($regionOptions as $region)
                            <option value="{{ $region->value }}">{{ $region->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(GeographicRegion::cases() as $region)
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $region->shortLabel() }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $countsByRegion[$region->value] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <div
                        wire:key="works-map-{{ $regionFilter }}-{{ count($markers) }}"
                        id="active-works-map"
                        class="h-[28rem] rounded-lg border border-gray-200 bg-gray-100 shadow-sm overflow-hidden"
                        data-center-lat="{{ $mapCenter['lat'] }}"
                        data-center-lng="{{ $mapCenter['lng'] }}"
                        data-zoom="{{ $mapZoom }}"
                        data-markers='@json($markers)'
                    ></div>
                    <p class="mt-2 text-xs text-gray-500">
                        Precisão: <strong>endereço</strong> (geocodificado) &gt; <strong>cache</strong> &gt; <strong>cidade</strong> &gt; <strong>região</strong>.
                        Execute <code class="text-[10px] bg-gray-100 px-1 rounded">php artisan rentals:geocode-worksites</code> para preencher locações antigas.
                    </p>
                </div>

                <div class="lg:col-span-2 space-y-4 max-h-[32rem] overflow-y-auto pr-1">
                    @forelse($grouped as $regionKey => $rentals)
                        @php $regionEnum = GeographicRegion::from($regionKey); @endphp
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $regionBadgeClass($regionKey) }}">
                                    {{ $regionEnum->label() }}
                                </span>
                                <span class="text-gray-500 font-normal">({{ $rentals->count() }})</span>
                            </h3>
                            <ul class="space-y-2">
                                @foreach($rentals as $rental)
                                    <li>
                                        <a
                                            href="{{ route('rentals.show', $rental) }}"
                                            wire:navigate
                                            class="block rounded-lg border border-gray-200 bg-white p-3 hover:border-indigo-300 hover:bg-indigo-50/40 transition"
                                            data-map-focus="{{ $rental->id }}"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <span class="font-medium text-sm text-indigo-700">{{ $rental->codigo }}</span>
                                                @if($rental->isReturnOverdue())
                                                    <span class="shrink-0 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">
                                                        {{ $rental->daysOverdue() }}d atraso
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs text-gray-600 line-clamp-2">{{ $rental->local_obra }}</p>
                                            @if($rental->hasObraCoordinates())
                                                <p class="mt-0.5 text-[10px] text-emerald-700">📍 Geocodificado</p>
                                            @endif
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $rental->customer?->nome }} · {{ $rental->asset?->codigo_patrimonio }}
                                            </p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                            Nenhuma locação em campo com local da obra informado.
                        </div>
                    @endforelse

                    @if($withoutAddress->isNotEmpty())
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <h3 class="text-sm font-semibold text-amber-900">Sem local da obra ({{ $withoutAddress->count() }})</h3>
                            <ul class="mt-2 space-y-1 text-xs text-amber-800">
                                @foreach($withoutAddress as $rental)
                                    <li>
                                        <a href="{{ route('rentals.show', $rental) }}" wire:navigate class="underline hover:text-amber-950">
                                            {{ $rental->codigo }} — {{ $rental->customer?->nome }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    const initActiveWorksMap = () => {
        const el = document.getElementById('active-works-map');
        if (!el || typeof L === 'undefined') {
            return;
        }

        if (el._leafletMap) {
            el._leafletMap.remove();
            el._leafletMap = null;
        }

        const center = [parseFloat(el.dataset.centerLat), parseFloat(el.dataset.centerLng)];
        const zoom = parseInt(el.dataset.zoom, 10) || 10;
        let markers = [];
        try {
            markers = JSON.parse(el.dataset.markers || '[]');
        } catch (_) {
            markers = [];
        }

        const map = L.map(el, { scrollWheelZoom: true }).setView(center, zoom);
        el._leafletMap = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        const regionColors = {
            bh: '#2563eb',
            rmbh: '#4f46e5',
            interior: '#d97706',
            indefinido: '#6b7280',
        };

        const bounds = [];
        const markerById = {};

        markers.forEach((item) => {
            const color = regionColors[item.region] || regionColors.indefinido;
            const isPrecise = item.precision === 'street' || item.precision === 'approximate';
            const size = isPrecise ? 16 : 14;
            const border = isPrecise ? '3px solid #fff' : '2px solid #fff';
            const icon = L.divIcon({
                className: '',
                html: `<span style="background:${color};width:${size}px;height:${size}px;border:${border};border-radius:50%;display:block;box-shadow:0 1px 4px rgba(0,0,0,.4)"></span>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
            });

            const overdue = item.overdue
                ? `<p style="margin:4px 0 0;color:#b91c1c;font-size:12px">Retorno em atraso (${item.days_overdue}d)</p>`
                : '';

            const popup = `
                <div style="min-width:180px;font-size:13px;line-height:1.4">
                    <strong>${item.codigo}</strong>
                    <span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:9999px;background:#e5e7eb;font-size:11px">${item.region_label}</span>
                    <p style="margin:6px 0 0">${item.local_obra}</p>
                    <p style="margin:4px 0 0;color:#6b7280;font-size:11px">Precisão: ${item.precision_label || item.precision}</p>
                    <p style="margin:4px 0 0;color:#4b5563">${item.customer} · ${item.asset}</p>
                    ${overdue}
                    <p style="margin:8px 0 0"><a href="${item.url}" style="color:#4f46e5">Abrir ficha</a></p>
                </div>
            `;

            const marker = L.marker([item.lat, item.lng], { icon }).addTo(map).bindPopup(popup);
            markerById[item.id] = marker;
            bounds.push([item.lat, item.lng]);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [32, 32], maxZoom: 13 });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 13);
        }

        document.querySelectorAll('[data-map-focus]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const id = parseInt(link.dataset.mapFocus, 10);
                const marker = markerById[id];
                if (marker) {
                    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 13));
                    marker.openPopup();
                }
            });
        });
    };

    initActiveWorksMap();
    document.addEventListener('livewire:navigated', initActiveWorksMap);
</script>
@endscript
