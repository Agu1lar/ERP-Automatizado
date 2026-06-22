<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Pessoas</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Cadastro de contatos e funcionários — da empresa ou de empresas parceiras/clientes.
                        Use os filtros abaixo; esta busca é exclusiva desta tela.
                    </p>
                </div>
                @can('create', App\Models\Domain\Person\Person::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">+ Nova pessoa</x-btn-primary>
                    @endunless
                @endcan
            </div>

            <div class="flex flex-wrap gap-3">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar por nome, contato ou endereço..."
                    class="rounded-md border-gray-300 shadow-sm max-w-md text-sm min-w-[16rem]"
                />
                <select wire:model.live="companyFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todas as empresas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->nome }}</option>
                    @endforeach
                </select>
                <select wire:model.live="companyTypeFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os tipos</option>
                    @foreach($companyTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    <option value="ativo">Ativos</option>
                    <option value="inativo">Inativos</option>
                </select>
                <x-archive-filter />
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4">
                        <div class="grid md:grid-cols-2 gap-4 max-w-4xl">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nome completo *</label>
                                <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">CPF *</label>
                                <input wire:model="cpf" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="000.000.000-00" />
                                @error('cpf') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Data de nascimento</label>
                                <input wire:model="data_nascimento" type="date" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cargo / função</label>
                                <input wire:model="cargo" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefone principal</label>
                                <input wire:model="telefone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefone secundário</label>
                                <input wire:model="telefone_secundario" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">E-mail</label>
                                <input wire:model="email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Empresa associada</label>
                                <select wire:model="company_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Nenhuma / autônomo</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->nome }} ({{ $company->typeEnum()->label() }})</option>
                                    @endforeach
                                </select>
                                @error('company_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4 max-w-4xl">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Endereço residencial</label>
                                <textarea wire:model="endereco_residencial" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Rua, número, bairro, cidade, CEP"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Endereço comercial</label>
                                <textarea wire:model="endereco_comercial" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Local de trabalho ou obra"></textarea>
                            </div>
                        </div>

                        <div class="max-w-4xl">
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
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPF</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contato</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @forelse($people as $person)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('people.show', $person) }}" wire:navigate data-tab-title="{{ $person->nome }}" class="text-indigo-600 hover:underline">
                                        {{ $person->nome }}
                                    </a>
                                    @if($person->cargo)
                                        <div class="text-xs text-gray-500">{{ $person->cargo }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $person->formattedCpf() }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $person->telefone ?? '—' }}</div>
                                    @if($person->email)
                                        <div class="text-xs text-gray-500">{{ $person->email }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $person->company?->nome ?? '—' }}
                                </td>
                                <td class="px-4 py-3">{{ $person->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        @unless($showArchived)
                                            @can('update', $person)
                                                <button wire:click="edit({{ $person->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                            @endcan
                                        @endunless
                                        <x-archive-record-button :model="$person" />
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhuma pessoa encontrada com os filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $people->links() }}</div>
            </div>
        </div>
    </div>
</div>
