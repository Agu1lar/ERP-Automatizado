<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="{{ route('maintenance.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar para OS</a>
                    <h2 class="text-xl font-semibold text-gray-800 mt-1">Regras de manutenção preventiva</h2>
                    <p class="text-sm text-gray-500">Defina intervalos por tipo de equipamento (modelo). Aplicam-se a todos os patrimônios daquele modelo.</p>
                </div>
                @can('create', App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class)
                    <x-btn-primary wire:click="create">+ Nova regra</x-btn-primary>
                @endcan
            </div>

            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar modelo ou descrição..." class="rounded-md border-gray-300 shadow-sm max-w-md" />

            @if($showForm)
                @can('create', App\Models\Domain\Maintenance\PreventiveMaintenanceRule::class)
                    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
                        <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar regra' : 'Nova regra' }}</h3>
                        <form wire:submit="save" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo de equipamento (modelo) *</label>
                                <select wire:model="equipment_model_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" @if($editingId) disabled @endif>
                                    <option value="">Selecione...</option>
                                    @foreach($models as $model)
                                        <option value="{{ $model->id }}">{{ $model->category->nome }} — {{ $model->displayName() }}</option>
                                    @endforeach
                                </select>
                                @error('equipment_model_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Intervalo (horas de uso) *</label>
                                <input wire:model="interval_horas" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Ex.: 250" />
                                @error('interval_horas') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Descrição da manutenção *</label>
                                <textarea wire:model="descricao" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Ex.: Troca de óleo e filtros"></textarea>
                                @error('descricao') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            @if($editingId)
                                <label class="flex items-center gap-2 text-sm">
                                    <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                                    Regra ativa
                                </label>
                            @endif
                            <div class="flex gap-2">
                                <x-btn-primary type="submit">Salvar</x-btn-primary>
                                <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                            </div>
                        </form>
                    </div>
                @endcan
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Equipamento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Intervalo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rules as $rule)
                            <tr class="text-sm">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ $rule->equipmentModel->displayName() }}</span>
                                    <p class="text-xs text-gray-500">{{ $rule->equipmentModel->category->nome }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">A cada {{ number_format($rule->interval_horas, 0, ',', '.') }} h</td>
                                <td class="px-4 py-3 text-gray-600">{{ $rule->descricao }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $rule->ativo,
                                        'bg-gray-100 text-gray-600' => ! $rule->ativo,
                                    ])>{{ $rule->ativo ? 'Ativa' : 'Inativa' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $rule)
                                        <button wire:click="edit({{ $rule->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhuma regra cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $rules->links() }}
        </div>
    </div>
</div>
