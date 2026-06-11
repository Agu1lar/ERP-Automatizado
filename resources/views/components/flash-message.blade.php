@if (session('success'))
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm flex flex-wrap items-center justify-between gap-3">
            <span>{{ session('success') }}</span>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if (session('return_to_rental_url'))
                    <a
                        href="{{ session('return_to_rental_url') }}"
                        wire:navigate
                        @if (session('return_to_rental_tab_title')) data-tab-title="{{ session('return_to_rental_tab_title') }}" @endif
                        class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-green-300 text-green-800 text-xs font-medium hover:bg-green-100"
                    >
                        {{ session('return_to_rental_label', 'Voltar à ficha') }}
                    </a>
                @endif
                @if (session('success_link'))
                    <a href="{{ session('success_link') }}" wire:navigate class="inline-flex items-center px-3 py-1.5 rounded-md bg-green-700 text-white text-xs font-medium hover:bg-green-800">
                        {{ session('success_link_label', 'Ver detalhes') }}
                    </a>
                @endif
                @foreach (session('success_actions', []) as $action)
                    <a
                        href="{{ $action['url'] }}"
                        wire:navigate
                        @class([
                            'inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium',
                            'bg-green-700 text-white hover:bg-green-800' => ! empty($action['primary']),
                            'bg-white border border-green-300 text-green-800 hover:bg-green-100' => empty($action['primary']),
                        ])
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md text-sm flex flex-wrap items-center justify-between gap-3">
            <span>{{ session('error') }}</span>
            @if (session('error_actions', []) !== [])
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @foreach (session('error_actions', []) as $action)
                        <a
                            href="{{ $action['url'] }}"
                            wire:navigate
                            @class([
                                'inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium',
                                'bg-red-700 text-white hover:bg-red-800' => ! empty($action['primary']),
                                'bg-white border border-red-300 text-red-800 hover:bg-red-100' => empty($action['primary']),
                            ])
                        >
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
