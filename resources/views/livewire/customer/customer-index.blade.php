<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Clientes</h2>
                @can('create', App\Models\Domain\Customer\Customer::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">+ Novo cliente</x-btn-primary>
                    @endunless
                @endcan
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar nome ou CPF/CNPJ..." class="rounded-md border-gray-300 shadow-sm max-w-md" />
                <x-archive-filter />
            </div>

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

                        <div class="rounded-lg border border-red-100 bg-red-50/40 p-4 space-y-3">
                            <p class="text-xs text-red-700">Bloqueio manual — decisão do comercial ou gestor. Inadimplência não bloqueia automaticamente.</p>
                            <label class="flex items-center gap-2">
                                <input wire:model.live="bloqueado" type="checkbox" class="rounded border-red-300 text-red-600" />
                                <span class="text-sm font-medium text-red-800">Cliente bloqueado</span>
                            </label>
                            @if($bloqueado)
                                <div>
                                    <label class="block text-sm font-medium text-red-800">Justificativa do bloqueio *</label>
                                    <textarea
                                        wire:model="motivo_bloqueio"
                                        rows="3"
                                        class="mt-1 w-full rounded-md border-red-200 shadow-sm text-sm"
                                        placeholder="Ex.: Inadimplência das parcelas 3 e 4 (venc. 15/03/2026) · Nome no SPC"
                                    ></textarea>
                                    @error('motivo_bloqueio') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>

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
                            <tr @class(['bg-red-50/40' => $customer->isBlockedForDisplay()])>
                                <td class="px-4 py-3 text-sm">
                                    <x-customer-blocked-name
                                        :name="$customer->nome"
                                        :blocked="$customer->isBlockedForDisplay()"
                                        :reason="$customer->rentalBlockReason()"
                                        :href="route('customers.show', $customer)"
                                        :tab-title="$customer->nome"
                                    />
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $customer->formattedDocument() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $customer->telefone ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if($customer->isManuallyBlocked())
                                        <span class="text-red-700 font-medium">Bloqueado</span>
                                    @elseif($customer->isBlockedForDisplay())
                                        <span class="text-amber-700">Inadimplente</span>
                                    @else
                                        {{ $customer->ativo ? 'Ativo' : 'Inativo' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        @unless($showArchived)
                                            @can('update', $customer)
                                                <button wire:click="edit({{ $customer->id }})" class="text-indigo-600 text-sm hover:underline">Editar</button>
                                            @endcan
                                        @endunless
                                        <x-archive-record-button :model="$customer" />
                                    </span>
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
