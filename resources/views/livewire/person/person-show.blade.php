<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="{{ route('people.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Voltar para pessoas</a>
                    <h2 class="text-xl font-semibold text-gray-800 mt-1">{{ $person->nome }}</h2>
                    @if($person->company)
                        <p class="text-sm text-gray-500">{{ $person->cargo ?? 'Contato' }} — {{ $person->company->nome }}</p>
                    @endif
                </div>
            </div>

            @can('update', $person)
                <form wire:submit="save" class="bg-white rounded-lg shadow p-6 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome completo *</label>
                            <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                            @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">CPF *</label>
                            <input wire:model="cpf" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
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
                                    <option value="{{ $company->id }}">{{ $company->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço residencial</label>
                            <textarea wire:model="endereco_residencial" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Endereço comercial</label>
                            <textarea wire:model="endereco_comercial" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Observações</label>
                        <textarea wire:model="observacoes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select wire:model="ativo" class="mt-1 rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>

                    <x-btn-primary type="submit">Salvar alterações</x-btn-primary>
                </form>
            @else
                <div class="bg-white rounded-lg shadow p-6 space-y-3 text-sm">
                    <div><span class="text-gray-500">CPF:</span> {{ $person->formattedCpf() }}</div>
                    <div><span class="text-gray-500">Contato:</span> {{ $person->primaryContact() ?? '—' }}</div>
                    <div><span class="text-gray-500">Empresa:</span> {{ $person->company?->nome ?? '—' }}</div>
                    <div><span class="text-gray-500">Endereço residencial:</span> {{ $person->endereco_residencial ?? '—' }}</div>
                    <div><span class="text-gray-500">Endereço comercial:</span> {{ $person->endereco_comercial ?? '—' }}</div>
                </div>
            @endcan
        </div>
    </div>
</div>
