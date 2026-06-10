@auth
<div
    x-data
    x-cloak
    class="border-b border-gray-200 bg-slate-50"
    aria-label="Abas do sistema"
>
    <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto py-2">
            <template x-for="tab in $store.workspace.tabs" :key="tab.id">
                <div
                    class="group flex max-w-[220px] shrink-0 items-center rounded-md border text-xs transition"
                    :class="tab.id === $store.workspace.activeId
                        ? 'border-indigo-300 bg-indigo-50 text-indigo-900 shadow-sm'
                        : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50'"
                >
                    <button
                        type="button"
                        @click="$store.workspace.switchTab(tab.id)"
                        class="truncate px-3 py-1.5 text-left font-medium"
                        :title="tab.title"
                        x-text="tab.title"
                    ></button>
                    <button
                        type="button"
                        @click="$store.workspace.closeTab(tab.id)"
                        class="rounded-r-md px-1.5 py-1.5 text-gray-400 hover:bg-black/5 hover:text-gray-700"
                        :class="{ 'opacity-40 pointer-events-none': $store.workspace.tabs.length <= 1 }"
                        title="Fechar aba"
                        aria-label="Fechar aba"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-2 py-2">
            <button
                type="button"
                @click="$store.workspace.openBlankTab()"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50"
                title="Nova aba"
                aria-label="Nova aba"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
            <span class="hidden text-[11px] text-gray-400 lg:inline">Ctrl+clique abre nova aba</span>
        </div>
    </div>
</div>
@endauth
