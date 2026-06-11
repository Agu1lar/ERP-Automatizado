@foreach($messages as $msg)
    @php
        $shortcuts = $msg['meta']['actions'] ?? $msg['meta']['result']['next_steps'] ?? [];
        $navLinks = array_values(array_filter($shortcuts, fn ($a) => ! empty($a['url'])));
        $commands = array_values(array_filter($shortcuts, fn ($a) => ! empty($a['command'])));
        $isUser = $msg['role'] === 'user';
    @endphp
    <div @class([
        'flex',
        'justify-end' => $isUser,
        'justify-start' => ! $isUser,
    ])>
        <div @class([
            'max-w-[92%] rounded-xl px-3.5 py-2.5 text-sm leading-relaxed',
            'bg-indigo-600 text-white' => $isUser,
            'bg-gray-100 text-gray-800' => ! $isUser,
        ])>
            <div class="whitespace-pre-wrap [&_strong]:font-semibold">{!! \App\Support\CopilotMessageFormatter::format($msg['content']) !!}</div>

            @if(! $isUser && ! empty($msg['meta']['result']['ok']))
                <p class="mt-2 text-xs text-emerald-700 font-medium">✓ Consulta concluída</p>
            @endif

            @if(! $isUser && count($navLinks) > 0)
                <div class="mt-3 border-t border-gray-200/80 pt-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Atalhos</p>
                    <div class="flex flex-col gap-1.5">
                        @foreach($navLinks as $action)
                            <a
                                href="{{ $action['url'] }}"
                                wire:navigate
                                wire:click="closePanel"
                                @class([
                                    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-medium transition',
                                    'bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm' => ! empty($action['primary']),
                                    'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50' => empty($action['primary']),
                                ])
                            >
                                {{ $action['label'] }}
                                <svg class="ml-1.5 h-3.5 w-3.5 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(! $isUser && count($commands) > 0)
                <div @class(['mt-3', 'border-t border-gray-200/80 pt-2.5' => count($navLinks) === 0])>
                    @if(count($navLinks) === 0)
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Sugestões</p>
                    @else
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Executar</p>
                    @endif
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($commands as $action)
                            <button
                                type="button"
                                wire:click='runAction(@js($action["command"]), @js($action["params"] ?? []))'
                                class="text-xs px-2.5 py-1.5 rounded-lg bg-indigo-500 text-white hover:bg-indigo-600 font-medium"
                            >
                                {{ $action['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endforeach
