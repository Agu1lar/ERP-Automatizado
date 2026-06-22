<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Empresas</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Cadastro de empresas com múltiplos contatos e e-mails — vincule pessoas em Cadastros → Pessoas.
                    </p>
                </div>
                @can('create', App\Models\Domain\Person\Company::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">+ Nova empresa</x-btn-primary>
                    @endunless
                @endcan
            </div>

            <div class="flex flex-wrap gap-3">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar por nome, contato, e-mail ou endereço..."
                    class="rounded-md border-gray-300 shadow-sm max-w-md text-sm"
                />
                <select wire:model.live="typeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os tipos</option>
                    @foreach($companyTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <x-archive-filter />
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-6 max-w-3xl">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Razão social / nome *</label>
                            <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">CNPJ</label>
                                <input wire:model="cnpj" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('cnpj') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo *</label>
                                <select wire:model="tipo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    @foreach($companyTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">Contatos</h3>
                                    <p class="text-xs text-gray-500">Adicione quantos contatos precisar (nome, cargo e telefone).</p>
                                </div>
                                <button type="button" wire:click="addContact" class="text-sm text-indigo-600 hover:underline">+ Contato</button>
                            </div>
                            @error('contacts') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="space-y-3">
                                @foreach($contacts as $index => $contact)
                                    <div wire:key="company-contact-{{ $index }}" class="rounded-lg border border-gray-200 p-4 space-y-3">
                                        <div class="grid sm:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Nome</label>
                                                <input wire:model="contacts.{{ $index }}.nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                                @error('contacts.'.$index.'.nome') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Cargo</label>
                                                <input wire:model="contacts.{{ $index }}.cargo" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Telefone</label>
                                                <input wire:model="contacts.{{ $index }}.telefone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                                <input
                                                    type="radio"
                                                    name="primary_contact"
                                                    wire:click="setPrimaryContact({{ $index }})"
                                                    @checked($contact['principal'] ?? false)
                                                />
                                                Contato principal
                                            </label>
                                            @if(count($contacts) > 1)
                                                <button type="button" wire:click="removeContact({{ $index }})" class="text-sm text-red-600 hover:underline">Remover</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">E-mails</h3>
                                    <p class="text-xs text-gray-500">Cadastre e-mails por departamento ou finalidade.</p>
                                </div>
                                <button type="button" wire:click="addEmail" class="text-sm text-indigo-600 hover:underline">+ E-mail</button>
                            </div>
                            <div class="space-y-3">
                                @foreach($emails as $index => $emailRow)
                                    <div wire:key="company-email-{{ $index }}" class="rounded-lg border border-gray-200 p-4 space-y-3">
                                        <div class="grid sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">E-mail</label>
                                                <input wire:model="emails.{{ $index }}.email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                                @error('emails.'.$index.'.email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Rótulo (ex.: Comercial, Financeiro)</label>
                                                <input wire:model="emails.{{ $index }}.rotulo" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                                <input
                                                    type="radio"
                                                    name="primary_email"
                                                    wire:click="setPrimaryEmail({{ $index }})"
                                                    @checked($emailRow['principal'] ?? false)
                                                />
                                                E-mail principal
                                            </label>
                                            @if(count($emails) > 1)
                                                <button type="button" wire:click="removeEmail({{ $index }})" class="text-sm text-red-600 hover:underline">Remover</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço comercial</label>
                            <textarea wire:model="endereco" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            Ativo
                        </label>
                        <div class="flex gap-2">
                            <x-btn-primary type="submit">Salvar</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contatos / e-mails</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pessoas</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($companies as $company)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $company->nome }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $company->typeEnum()->label() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $company->contactSummary() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $company->people_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        @unless($showArchived)
                                            @can('update', $company)
                                                <button wire:click="edit({{ $company->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                            @endcan
                                        @endunless
                                        <x-archive-record-button :model="$company" />
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhuma empresa cadastrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $companies->links() }}</div>
            </div>
        </div>
    </div>
</div>
