<?php

namespace App\Livewire\Person;

use App\Enums\CompanyType;
use App\Livewire\Concerns\ArchivesRecords;
use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Rules\ValidCpf;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PersonIndex extends Component
{
    use ArchivesRecords, AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $companyFilter = '';

    public string $companyTypeFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $cpf = '';

    public string $data_nascimento = '';

    public string $telefone = '';

    public string $telefone_secundario = '';

    public string $email = '';

    public string $cargo = '';

    public ?int $company_id = null;

    public string $endereco_residencial = '';

    public string $endereco_comercial = '';

    public string $observacoes = '';

    public bool $ativo = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Person::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCompanyFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCompanyTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Person::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $person = Person::findOrFail($id);
        $this->authorize('update', $person);

        $this->editingId = $person->id;
        $this->nome = $person->nome;
        $this->cpf = $person->formattedCpf();
        $this->data_nascimento = $person->data_nascimento?->format('Y-m-d') ?? '';
        $this->telefone = $person->telefone ?? '';
        $this->telefone_secundario = $person->telefone_secundario ?? '';
        $this->email = $person->email ?? '';
        $this->cargo = $person->cargo ?? '';
        $this->company_id = $person->company_id;
        $this->endereco_residencial = $person->endereco_residencial ?? '';
        $this->endereco_comercial = $person->endereco_comercial ?? '';
        $this->observacoes = $person->observacoes ?? '';
        $this->ativo = $person->ativo;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'cpf' => ['required', 'string', 'max:14', 'unique:people,cpf'.($this->editingId ? ','.$this->editingId : ''), new ValidCpf],
            'data_nascimento' => 'nullable|date',
            'telefone' => 'nullable|string|max:30',
            'telefone_secundario' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'endereco_residencial' => 'nullable|string',
            'endereco_comercial' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'ativo' => 'boolean',
        ]);

        $payload = [
            ...$data,
            'cpf' => preg_replace('/\D/', '', $data['cpf']),
            'data_nascimento' => filled($data['data_nascimento']) ? $data['data_nascimento'] : null,
            'company_id' => $data['company_id'] ?: null,
            'telefone' => filled($data['telefone']) ? $data['telefone'] : null,
            'telefone_secundario' => filled($data['telefone_secundario']) ? $data['telefone_secundario'] : null,
            'email' => filled($data['email']) ? $data['email'] : null,
            'cargo' => filled($data['cargo']) ? $data['cargo'] : null,
            'endereco_residencial' => filled($data['endereco_residencial']) ? $data['endereco_residencial'] : null,
            'endereco_comercial' => filled($data['endereco_comercial']) ? $data['endereco_comercial'] : null,
            'observacoes' => filled($data['observacoes']) ? $data['observacoes'] : null,
        ];

        if ($this->editingId) {
            $person = Person::findOrFail($this->editingId);
            $this->authorize('update', $person);
            $person->update($payload);
        } else {
            $this->authorize('create', Person::class);
            Person::create([
                ...$payload,
                'created_by' => auth()->id(),
            ]);
        }

        $this->resetForm();
        session()->flash('success', 'Pessoa salva com sucesso.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        $people = $this->archivableQuery(Person::class)
            ->with('company')
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->when($this->companyFilter, fn ($query) => $query->where('company_id', $this->companyFilter))
            ->when($this->companyTypeFilter, function ($query) {
                $query->whereHas('company', fn ($company) => $company->where('tipo', $this->companyTypeFilter));
            })
            ->when($this->statusFilter === 'ativo', fn ($query) => $query->where('ativo', true))
            ->when($this->statusFilter === 'inativo', fn ($query) => $query->where('ativo', false))
            ->orderBy('nome')
            ->paginate(20);

        return view('livewire.person.person-index', [
            'people' => $people,
            'companies' => Company::query()->where('ativo', true)->orderBy('nome')->get(),
            'companyTypes' => CompanyType::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->nome = '';
        $this->cpf = '';
        $this->data_nascimento = '';
        $this->telefone = '';
        $this->telefone_secundario = '';
        $this->email = '';
        $this->cargo = '';
        $this->company_id = null;
        $this->endereco_residencial = '';
        $this->endereco_comercial = '';
        $this->observacoes = '';
        $this->ativo = true;
        $this->resetValidation();
    }
}
