<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Copiloto</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Assistente operacional — usa os mesmos comandos da API do agente.
                    @if(config('agent.llm.enabled') && config('agent.llm.api_key'))
                        <span class="text-emerald-600">Modelo LLM ativo.</span>
                    @else
                        <span class="text-gray-400">Modo heurístico (configure AGENT_LLM_* no .env para IA).</span>
                    @endif
                </p>
            </div>

            <div class="bg-white rounded-lg shadow flex flex-col min-h-[420px]">
                <div class="flex-1 p-4 space-y-4 overflow-y-auto max-h-[55vh]" id="copilot-messages">
                    @foreach($messages as $msg)
                        <div @class([
                            'flex',
                            'justify-end' => $msg['role'] === 'user',
                            'justify-start' => $msg['role'] !== 'user',
                        ])>
                            <div @class([
                                'max-w-[85%] rounded-lg px-4 py-3 text-sm',
                                'bg-indigo-600 text-white' => $msg['role'] === 'user',
                                'bg-gray-100 text-gray-800' => $msg['role'] !== 'user',
                            ])>
                                <p class="whitespace-pre-wrap">{{ $msg['content'] }}</p>

                                @if(!empty($msg['meta']['result']['ok']))
                                    <p class="mt-2 text-xs opacity-80">✓ Comando executado</p>
                                @endif

                                @if(!empty($msg['meta']['result']['next_steps']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($msg['meta']['result']['next_steps'] as $action)
                                            @if(!empty($action['command']))
                                                <button
                                                    type="button"
                                                    wire:click='runAction(@js($action["command"]), @js($action["params"] ?? []))'
                                                    class="text-xs px-2 py-1 rounded bg-emerald-600/90 text-white hover:bg-emerald-700"
                                                >
                                                    {{ $action['label'] }}
                                                </button>
                                            @elseif(!empty($action['url']))
                                                <a href="{{ $action['url'] }}" wire:navigate class="text-xs px-2 py-1 rounded bg-white/20 hover:bg-white/30 border border-gray-300 text-gray-800">
                                                    {{ $action['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                @if(!empty($msg['meta']['actions']))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($msg['meta']['actions'] as $action)
                                            @if(!empty($action['command']))
                                                <button
                                                    type="button"
                                                    wire:click='runAction(@js($action["command"]), @js($action["params"] ?? []))'
                                                    class="text-xs px-2 py-1 rounded bg-indigo-500 text-white hover:bg-indigo-600"
                                                >
                                                    {{ $action['label'] }}
                                                </button>
                                            @elseif(!empty($action['url']))
                                                <a href="{{ $action['url'] }}" wire:navigate class="text-xs px-2 py-1 rounded bg-white/20 hover:bg-white/30 border border-white/30">
                                                    {{ $action['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($pendingCommand)
                    <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                        <p class="font-medium text-amber-900">Confirmar ação: <code class="text-xs">{{ $pendingCommand }}</code></p>
                        <pre class="text-xs text-amber-800 mt-1 overflow-x-auto">{{ json_encode($pendingInput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

                        @if($pendingPreview && ($pendingPreview['ok'] ?? false))
                            <div class="mt-3 p-3 rounded-md bg-white border border-amber-200">
                                <p class="text-xs font-medium text-amber-900">Prévia (dry-run)</p>
                                <p class="text-xs text-amber-800 mt-1">{{ $pendingPreview['message'] ?? '' }}</p>
                                @if(!empty($pendingPreview['data']))
                                    <pre class="text-xs text-gray-600 mt-2 overflow-x-auto max-h-32">{{ json_encode($pendingPreview['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </div>
                        @endif

                        <div class="mt-2 flex gap-2">
                            <button type="button" wire:click="confirmPending" class="px-3 py-1.5 rounded-md bg-amber-600 text-white text-xs font-medium hover:bg-amber-700">
                                Confirmar
                            </button>
                            <button type="button" wire:click="cancelPending" class="px-3 py-1.5 rounded-md border border-amber-300 text-amber-800 text-xs hover:bg-amber-100">
                                Cancelar
                            </button>
                        </div>
                    </div>
                @endif

                <form wire:submit="sendMessage" class="border-t border-gray-100 p-4 flex gap-2">
                    <input
                        wire:model="prompt"
                        type="text"
                        placeholder="Ex.: resumo financeiro · retorno LOC-000012 · faturar FAT-000003"
                        class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autocomplete="off"
                    />
                    <x-btn-primary type="submit" class="text-sm shrink-0">Enviar</x-btn-primary>
                </form>
            </div>

            <details class="bg-white rounded-lg shadow p-4 text-sm">
                <summary class="font-medium text-gray-800 cursor-pointer">Comandos disponíveis ({{ count($commands) }})</summary>
                <ul class="mt-3 space-y-2 text-gray-600">
                    @foreach($commands as $cmd)
                        <li>
                            <code class="text-indigo-700">{{ $cmd['name'] }}</code>
                            — {{ $cmd['description'] }}
                        </li>
                    @endforeach
                </ul>
            </details>
        </div>
    </div>
</div>
