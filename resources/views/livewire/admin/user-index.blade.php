<x-flash-message />

<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Usuários</h2>
                @can('create', App\Models\User::class)
                    @unless($showArchived)
                        <x-btn-primary wire:click="create">+ Novo usuário</x-btn-primary>
                    @endunless
                @endcan
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar..." class="rounded-md border-gray-300 shadow-sm max-w-md" />
                <x-archive-filter />
            </div>

            @if($showForm)
                <div class="bg-white rounded-lg shadow p-6">
                    <form wire:submit="save" class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input wire:model="name" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input wire:model="email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Perfil</label>
                            <select wire:model="role" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione...</option>
                                @foreach(\App\Enums\UserRole::cases() as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                            @error('role') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ $editingId ? 'Nova senha (opcional)' : 'Senha *' }}</label>
                            <input wire:model="password" type="password" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            @error('password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">E-mail</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Último login</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($users as $user)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-sm">{{ $user->roles->first()?->name ? \App\Enums\UserRole::from($user->roles->first()->name)->label() : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $user->ultimo_login?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center gap-3">
                                        @unless($showArchived)
                                            @can('update', $user)
                                                <button wire:click="edit({{ $user->id }})" class="text-indigo-600 text-sm hover:underline">Editar</button>
                                            @endcan
                                        @endunless
                                        <x-archive-record-button :model="$user" />
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</div>
