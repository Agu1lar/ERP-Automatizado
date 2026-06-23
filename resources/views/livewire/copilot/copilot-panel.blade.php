<div
    class="fixed bottom-4 right-4 z-[70] flex flex-col items-end gap-3 pointer-events-none"
    x-data="{
        scrollBottom() {
            const el = this.$refs.messages;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        }
    }"
    x-on:copilot-scroll-bottom.window="scrollBottom()"
>
    @if($isOpen)
        <div
            class="pointer-events-auto flex w-[min(calc(100vw-2rem),26rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl sm:w-[28rem]"
            style="height: min(42rem, calc(100vh - 5rem)); max-height: min(42rem, calc(100vh - 5rem));"
        >
            <div class="flex shrink-0 items-start justify-between gap-2 border-b border-gray-100 bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-3 text-white">
                <div class="min-w-0">
                    <p class="text-sm font-semibold">Copiloto</p>
                    <p class="text-[11px] text-indigo-100 truncate" title="{{ $pageSummary }}">
                        {{ $pageLabel }}@if($pageDetail) — {{ $pageDetail }}@endif
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="closePanel"
                    class="shrink-0 rounded-md p-1 text-indigo-100 hover:bg-white/10 hover:text-white"
                    title="Minimizar"
                    aria-label="Minimizar copiloto"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            <div class="shrink-0 border-b border-gray-100 bg-gray-50 px-3 py-2">
                <div class="flex rounded-lg bg-gray-200/80 p-0.5 text-xs font-medium">
                    <button
                        type="button"
                        wire:click="setMode('ask')"
                        @class([
                            'flex-1 rounded-md px-2 py-1.5 transition',
                            'bg-white text-indigo-700 shadow-sm' => $mode === 'ask',
                            'text-gray-600 hover:text-gray-800' => $mode !== 'ask',
                        ])
                    >
                        Pergunta
                    </button>
                    <button
                        type="button"
                        wire:click="setMode('agent')"
                        @class([
                            'flex-1 rounded-md px-2 py-1.5 transition',
                            'bg-white text-indigo-700 shadow-sm' => $mode === 'agent',
                            'text-gray-600 hover:text-gray-800' => $mode !== 'agent',
                        ])
                    >
                        Agente
                    </button>
                </div>
                <p class="mt-1.5 text-[10px] text-gray-500 leading-snug">
                    @if($mode === 'ask')
                        Só consulta e análise — nunca grava no sistema.
                    @else
                        Pode cadastrar e avançar fluxos após sua confirmação.
                    @endif
                    @if(!($llmEnabled ?? false))
                        @if($llmConfigured ?? false)
                            <span class="text-amber-600"> IA configurada, mas indisponível — verifique chave/créditos.</span>
                        @else
                            <span class="text-amber-600"> Leitura inteligente de documentos não habilitada.</span>
                        @endif
                    @endif
                </p>
            </div>

            @if($llmDegraded)
                <div class="shrink-0 border-b border-amber-300 bg-amber-100 px-3 py-2 text-[11px] leading-snug text-amber-950">
                    {!! \App\Support\CopilotMessageFormatter::format($llmDegradationNotice ?? 'Inteligência operacional indisponível.') !!}
                </div>
            @endif

            <div
                x-ref="messages"
                class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3"
            >
                @include('livewire.copilot.partials.chat-messages')
            </div>

            @if($requiresInput && $pendingCommand && $mode === 'agent')
                <div class="shrink-0 border-t border-sky-200 bg-sky-50 px-3 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Informações pendentes</p>
                    <p class="mt-1 text-xs text-sky-900">
                        Ação: <strong>{{ $pendingActionLabel ?? 'Em andamento' }}</strong>
                    </p>
                    @if($inputRequest)
                        @if(!empty($inputRequest['missing']))
                            <ul class="mt-2 space-y-1 text-xs text-sky-900 list-disc list-inside">
                                @foreach($inputRequest['missing'] as $field)
                                    <li><strong>{{ $field['label'] ?? $field['key'] }}</strong>@if(!empty($field['hint'])) — {{ $field['hint'] }}@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($inputRequest['recommended']))
                            <p class="mt-2 text-[11px] font-medium text-sky-800">Opcional (recomendado):</p>
                            <ul class="mt-1 space-y-0.5 text-[11px] text-sky-800 list-disc list-inside">
                                @foreach($inputRequest['recommended'] as $field)
                                    <li>{{ $field['label'] ?? $field['key'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                    <p class="mt-2 text-[11px] text-sky-700">Digite os dados no chat abaixo ou envie <strong>cancelar</strong> para desistir.</p>
                    <div class="mt-2">
                        <button type="button" wire:click="cancelPending" class="px-3 py-1.5 rounded-lg border border-sky-300 text-sky-800 text-xs hover:bg-sky-100">
                            Cancelar operação
                        </button>
                    </div>
                </div>
            @elseif($pendingCommand && $mode === 'agent' && ! $requiresInput)
                <div class="shrink-0 border-t border-amber-200 bg-amber-50 px-3 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Deseja que eu faça?</p>
                    <p class="mt-1 text-xs text-amber-900">
                        Ação: <strong>{{ $pendingActionLabel ?? 'Confirmar execução' }}</strong>
                    </p>
                    @php
                        $structuredPreview = $pendingPreview['action_preview'] ?? $pendingPreview ?? null;
                    @endphp
                    @if($structuredPreview)
                        <x-copilot-action-preview :preview="$structuredPreview" />
                    @endif
                    <p class="mt-2 text-[11px] text-amber-700">Confirme para executar ou cancele para fazer manualmente na tela.</p>
                    <div class="mt-2.5 flex flex-wrap gap-2">
                        <button type="button" wire:click="confirmPending" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-medium hover:bg-amber-700">
                            Sim, executar agora
                        </button>
                        <button type="button" wire:click="queuePendingInBackground" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
                            Executar em background
                        </button>
                        <button type="button" wire:click="cancelPending" class="px-3 py-1.5 rounded-lg border border-amber-300 text-amber-800 text-xs hover:bg-amber-100">
                            Cancelar
                        </button>
                    </div>
                </div>
            @endif

            <form wire:submit="sendMessage" class="shrink-0 border-t border-gray-100 p-3 pointer-events-auto bg-white space-y-2">
                @if(count($queuedAttachments) > 0)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($queuedAttachments as $index => $file)
                            <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-[11px] text-indigo-800">
                                📎 {{ Str::limit($file['original_name'], 24) }}
                                <button type="button" wire:click="removeAttachment({{ $index }})" class="text-indigo-500 hover:text-indigo-800">×</button>
                            </span>
                        @endforeach
                    </div>
                @endif
                <div class="flex gap-2">
                    <label class="shrink-0 cursor-pointer rounded-lg border border-gray-300 px-2.5 py-2 text-gray-600 hover:bg-gray-50" title="Anexar documento">
                        <input type="file" wire:model="attachment" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx" />
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </label>
                    <input
                        wire:model="prompt"
                        type="text"
                        placeholder="{{ $mode === 'agent' ? 'Ex.: extraia o cliente do anexo e cadastre…' : 'Ex.: analise o contrato anexo…' }}"
                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autocomplete="off"
                    />
                    <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Enviar
                    </button>
                </div>
                @error('attachment') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="attachment" class="text-[10px] text-gray-500">Enviando arquivo…</div>
            </form>
        </div>
    @endif

    <button
        type="button"
        wire:click="togglePanel"
        @class([
            'pointer-events-auto flex h-14 w-14 items-center justify-center rounded-full shadow-lg transition transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500',
            'bg-indigo-600 text-white hover:bg-indigo-700' => ! $isOpen,
            'bg-gray-800 text-white hover:bg-gray-900' => $isOpen,
        ])
        aria-label="{{ $isOpen ? 'Minimizar copiloto' : 'Abrir copiloto' }}"
        title="Copiloto"
    >
        @if($isOpen)
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        @else
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.847-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
            </svg>
        @endif
    </button>
</div>
