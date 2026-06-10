<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class UserIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? '';
        $this->ativo = $user->ativo;
        $this->password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email'.($this->editingId ? ','.$this->editingId : ''),
            'role' => 'required|in:'.implode(',', array_column(UserRole::cases(), 'value')),
            'ativo' => 'boolean',
        ];

        if (! $this->editingId) {
            $rules['password'] = ['required', Password::min(8)];
        } elseif (filled($this->password)) {
            $rules['password'] = [Password::min(8)];
        }

        $data = $this->validate($rules);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $this->authorize('update', $user);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'ativo' => $data['ativo'],
            ]);

            if (filled($this->password)) {
                $user->update(['password' => Hash::make($this->password)]);
            }
        } else {
            $this->authorize('create', User::class);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'ativo' => $data['ativo'],
            ]);
        }

        $user->syncRoles([$data['role']]);

        $this->resetForm();
        session()->flash('success', 'Usuário salvo com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = '';
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->paginate(15);

        $roles = Role::orderBy('name')->get();

        return view('livewire.admin.user-index', compact('users', 'roles'));
    }
}
