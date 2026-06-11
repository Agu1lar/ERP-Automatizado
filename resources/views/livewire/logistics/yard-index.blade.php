<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h2 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                    Pátios e filiais
                    <x-help-hint text="Cadastro de pátios de origem dos patrimônios (ex.: BH principal, Contagem, Betim). Usado na logística e na ficha do equipamento." />
                </h2>
                @can('create', App\Models\Domain\Logistics\Yard::class)
                    <x-btn-primary wire:click="create">+ Novo pátio</x-btn-primary>
                @endcan
            </div>

            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar pátio..." class="w-full max-w-md rounded-md border-gray-300 shadow-sm" />

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="Ex.: Pátio BH Principal" />
                            @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cidade</label>
                            <input wire:model="cidade" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" placeholder="Ex.: Belo Horizonte" />
                            @error('cidade') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço</label>
                            <input wire:model="endereco" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('endereco') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Telefone</label>
                            <input wire:model="telefone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('telefone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm text-gray-700">Ativo</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input wire:model="principal" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm text-gray-700">Pátio principal da empresa</span>
                        </label>
                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cidade</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônios</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($yards as $yard)
                            <tr class="hover:bg-gray-50/80">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $yard->nome }}
                                    @if($yard->principal)
                                        <span class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-indigo-700">Principal</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $yard->cidade ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $yard->assets_count }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="{{ $yard->ativo ? 'text-green-600' : 'text-gray-400' }}">{{ $yard->ativo ? 'Ativo' : 'Inativo' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $yard)
                                        <button wire:click="edit({{ $yard->id }})" class="text-indigo-600 text-sm hover:underline">Editar</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Nenhum pátio cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $yards->links() }}</div>
            </div>
        </div>
    </div>
</div>
