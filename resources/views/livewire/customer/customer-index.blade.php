<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Clientes</h2>
                @can('create', App\Models\Domain\Customer\Customer::class)
                    <x-btn-primary wire:click="create">+ Novo cliente</x-btn-primary>
                @endcan
            </div>

            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar nome ou CPF/CNPJ..." class="rounded-md border-gray-300 shadow-sm max-w-md" />

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-2xl">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome *</label>
                            <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">CPF/CNPJ *</label>
                            <input wire:model="cpf_cnpj" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('cpf_cnpj') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Contato</label>
                                <input wire:model="contato" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefone</label>
                                <input wire:model="telefone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input wire:model="email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço</label>
                            <textarea wire:model="endereco" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm">Ativo</span>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPF/CNPJ</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($customers as $customer)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium">
                                    <a href="{{ route('customers.show', $customer) }}" wire:navigate data-tab-title="{{ $customer->nome }}" class="text-indigo-600 hover:underline">{{ $customer->nome }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $customer->formattedDocument() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $customer->telefone ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">{{ $customer->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('update', $customer)
                                        <button wire:click="edit({{ $customer->id }})" class="text-indigo-600 text-sm hover:underline">Editar</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $customers->links() }}</div>
            </div>
        </div>
    </div>
</div>
