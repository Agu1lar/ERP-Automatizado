<?php

namespace App\Livewire\Customer;

use App\Livewire\Concerns\ArchivesRecords;
use App\Models\Domain\Customer\Customer;
use App\Rules\ValidCpfCnpj;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CustomerIndex extends Component
{
    use ArchivesRecords, AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $cpf_cnpj = '';

    public string $contato = '';

    public string $telefone = '';

    public string $email = '';

    public string $endereco = '';

    public bool $ativo = true;

    public bool $bloqueado = false;

    public string $motivo_bloqueio = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Customer::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('update', $customer);

        $this->editingId = $customer->id;
        $this->nome = $customer->nome;
        $this->cpf_cnpj = $customer->cpf_cnpj;
        $this->contato = $customer->contato ?? '';
        $this->telefone = $customer->telefone ?? '';
        $this->email = $customer->email ?? '';
        $this->endereco = $customer->endereco ?? '';
        $this->ativo = $customer->ativo;
        $this->bloqueado = $customer->bloqueado;
        $this->motivo_bloqueio = $customer->motivo_bloqueio ?? '';
        $this->showForm = true;
    }

    public function updatedBloqueado(bool $value): void
    {
        if (! $value) {
            $this->motivo_bloqueio = '';
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'cpf_cnpj' => ['required', 'string', 'max:20', 'unique:customers,cpf_cnpj'.($this->editingId ? ','.$this->editingId : ''), new ValidCpfCnpj],
            'contato' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string',
            'ativo' => 'boolean',
            'bloqueado' => 'boolean',
            'motivo_bloqueio' => 'required_if:bloqueado,true|nullable|string|max:2000',
        ]);

        $data['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);
        $data['motivo_bloqueio'] = filled($data['motivo_bloqueio'] ?? null) ? trim($data['motivo_bloqueio']) : null;
        Customer::applyManualBlockPayload($data);

        if ($this->editingId) {
            $customer = Customer::findOrFail($this->editingId);
            $this->authorize('update', $customer);
            $customer->update($data);
        } else {
            $this->authorize('create', Customer::class);
            Customer::create([
                ...$data,
                'created_by' => auth()->id(),
            ]);
        }

        $this->resetForm();
        session()->flash('success', 'Cliente salvo com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->nome = '';
        $this->cpf_cnpj = '';
        $this->contato = '';
        $this->telefone = '';
        $this->email = '';
        $this->endereco = '';
        $this->ativo = true;
        $this->bloqueado = false;
        $this->motivo_bloqueio = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        $customers = $this->archivableQuery(Customer::class)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('nome', 'like', '%'.$this->search.'%')
                    ->orWhere('cpf_cnpj', 'like', '%'.preg_replace('/\D/', '', $this->search).'%');
            }))
            ->orderBy('nome')
            ->paginate(20);

        return view('livewire.customer.customer-index', compact('customers'));
    }
}
