<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Empresas operacionais</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Dados de cada CNPJ usados em contratos, PDFs e exportações. O sistema {{ config('app.name') }} agrupa as duas empresas.
                    </p>
                </div>
                <x-archive-filter />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($companies as $company)
                    <div @class([
                        'bg-white rounded-lg shadow p-5 border-2 transition',
                        'border-indigo-500' => $editingId === $company->id,
                        'border-transparent' => $editingId !== $company->id,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $company->nome }}</h3>
                                @if($company->formattedCnpj())
                                    <p class="text-sm text-gray-500">CNPJ {{ $company->formattedCnpj() }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-1">Slug: {{ $company->slug }}</p>
                            </div>
                            <span @class([
                                'text-xs font-medium px-2 py-1 rounded-full',
                                'bg-emerald-100 text-emerald-800' => $company->ativo,
                                'bg-gray-100 text-gray-600' => ! $company->ativo,
                            ])>
                                {{ $company->ativo ? 'Ativa' : 'Inativa' }}
                            </span>
                        </div>

                        <dl class="mt-4 space-y-1 text-sm text-gray-600">
                            <div><span class="text-gray-400">Razão social:</span> {{ $company->razao_social ?? '—' }}</div>
                            <div><span class="text-gray-400">Endereço:</span> {{ $company->endereco ?? '—' }}</div>
                            <div><span class="text-gray-400">Contato:</span> {{ $company->telefone ?? '—' }} @if($company->email)— {{ $company->email }}@endif</div>
                        </dl>

                        @if($editingId !== $company->id)
                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                @unless($showArchived)
                                    <button
                                        type="button"
                                        wire:click="edit({{ $company->id }})"
                                        class="text-sm text-indigo-600 hover:underline font-medium"
                                    >
                                        Editar dados e logo
                                    </button>
                                @endunless
                                <x-archive-record-button :model="$company" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($editingId)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Editar empresa</h3>
                    <form wire:submit="save" class="space-y-4 max-w-2xl">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nome comercial *</label>
                                <input wire:model="nome" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('nome') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Razão social</label>
                                <input wire:model="razao_social" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('razao_social') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">CNPJ</label>
                                <input wire:model="cnpj" type="text" placeholder="00.000.000/0000-00" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('cnpj') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Telefone</label>
                                <input wire:model="telefone" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('telefone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">E-mail</label>
                                <input wire:model="email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Endereço</label>
                                <input wire:model="endereco" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('endereco') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Caminho do logo (opcional)</label>
                                <input wire:model="logo_path" type="text" placeholder="stack/assets/logo.png" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('logo_path') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ou enviar novo logo</label>
                                <input wire:model="logo_upload" type="file" accept="image/*" class="mt-1 w-full text-sm" />
                                @error('logo_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <label class="flex items-center gap-2">
                            <input wire:model="ativo" type="checkbox" class="rounded border-gray-300" />
                            <span class="text-sm text-gray-700">Empresa ativa (visível na troca de abas)</span>
                        </label>

                        <div class="flex gap-2 pt-2">
                            <x-btn-primary type="submit">Salvar empresa</x-btn-primary>
                            <x-btn-secondary type="button" wire:click="cancel">Cancelar</x-btn-secondary>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
