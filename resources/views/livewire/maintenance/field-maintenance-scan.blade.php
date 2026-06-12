<div class="px-4 py-4 space-y-4 max-w-lg mx-auto">
    <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Manutenção em campo</p>
        <h1 class="text-xl font-bold text-gray-900">{{ $asset->codigo_patrimonio }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ $asset->equipmentDisplayName() }}</p>
        <p class="text-xs text-gray-500 mt-2">Status: {{ $asset->statusEnum()->label() }}</p>
    </div>

    @if($activeRental)
        <div class="bg-white rounded-xl shadow p-4 text-sm">
            <p class="text-xs text-gray-500 uppercase">Locação em campo</p>
            <p class="font-semibold text-gray-900">{{ $activeRental->codigo }}</p>
            <p class="text-gray-600">{{ $activeRental->customer?->nome }}</p>
            @if($activeRental->local_obra)
                <p class="text-xs text-gray-500 mt-2">{{ $activeRental->local_obra }}</p>
            @endif
        </div>
    @endif

    @if($mode === 'open' && auth()->user()?->can('maintenance.operate'))
        <form wire:submit="openOrder" class="bg-white rounded-xl shadow p-4 space-y-4">
            <h2 class="font-semibold text-gray-900">Abrir OS em campo</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700">Problema / serviço</label>
                <textarea wire:model="descricao_problema" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Ex.: troca de mangueira, ajuste elétrico na obra"></textarea>
                @error('descricao_problema') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700">
                Abrir ordem de serviço
            </button>
        </form>
    @elseif($mode === 'complete' && $openFieldOrder && auth()->user()?->can('maintenance.operate'))
        <div class="bg-white rounded-xl shadow p-4 space-y-4">
            <div>
                <p class="text-xs text-gray-500 uppercase">OS em andamento</p>
                <p class="font-semibold text-indigo-700">{{ $openFieldOrder->codigo }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $openFieldOrder->descricao_problema }}</p>
            </div>

            <form wire:submit="completeOrder" class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-800">Checklist de conclusão</h3>
                @foreach($checklistLabels as $key => $label)
                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200">
                        <input type="checkbox" wire:model="checklist.{{ $key }}" class="mt-1 rounded border-gray-300 text-indigo-600" />
                        <span class="text-sm text-gray-800">{{ $label }}</span>
                    </label>
                @endforeach

                <div>
                    <label class="block text-sm font-medium text-gray-700">Horímetro (opcional)</label>
                    <input wire:model="horimetro" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Solução aplicada</label>
                    <textarea wire:model="solucao" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Resumo do que foi feito"></textarea>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-semibold text-sm hover:bg-emerald-700">
                    Concluir OS em campo
                </button>
            </form>
        </div>
    @elseif($mode === 'view')
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-600">
            @if(! $activeRental)
                Manutenção em campo disponível apenas com patrimônio <strong>locado</strong> em obra.
            @else
                Nenhuma OS de campo aberta. Use o formulário acima quando estiver pronto para registrar o serviço.
            @endif
        </div>
    @endif

    <div class="text-center">
        <a href="{{ route('maintenance.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Ver todas as OS →</a>
    </div>
</div>
