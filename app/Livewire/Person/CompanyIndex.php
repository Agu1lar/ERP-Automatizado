<?php

namespace App\Livewire\Person;

use App\Enums\CompanyType;
use App\Models\Domain\Person\Company;
use App\Rules\ValidCpfCnpj;
use App\Services\CompanyService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CompanyIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $cnpj = '';

    public string $tipo = CompanyType::Externa->value;

    public string $endereco = '';

    public string $observacoes = '';

    public bool $ativo = true;

    /** @var list<array{nome: string, cargo: string, telefone: string, principal: bool}> */
    public array $contacts = [];

    /** @var list<array{email: string, rotulo: string, principal: bool}> */
    public array $emails = [];

    public function mount(CompanyService $companyService): void
    {
        $this->authorize('viewAny', Company::class);
        $this->contacts = [$companyService->emptyContactRow()];
        $this->emails = [$companyService->emptyEmailRow()];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function create(CompanyService $companyService): void
    {
        $this->authorize('create', Company::class);
        $this->resetForm($companyService);
        $this->showForm = true;
    }

    public function edit(int $id, CompanyService $companyService): void
    {
        $company = Company::with(['contacts', 'emails'])->findOrFail($id);
        $this->authorize('update', $company);

        $this->editingId = $company->id;
        $this->nome = $company->nome;
        $this->cnpj = $company->formattedCnpj() ?? '';
        $this->tipo = $company->tipo;
        $this->endereco = $company->endereco ?? '';
        $this->observacoes = $company->observacoes ?? '';
        $this->ativo = $company->ativo;
        $this->contacts = $companyService->contactsToForm($company);
        $this->emails = $companyService->emailsToForm($company);
        $this->showForm = true;
    }

    public function addContact(): void
    {
        $this->contacts[] = app(CompanyService::class)->emptyContactRow();
    }

    public function removeContact(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);

        if ($this->contacts === []) {
            $this->contacts = [app(CompanyService::class)->emptyContactRow()];
        }
    }

    public function addEmail(): void
    {
        $this->emails[] = app(CompanyService::class)->emptyEmailRow();
    }

    public function removeEmail(int $index): void
    {
        unset($this->emails[$index]);
        $this->emails = array_values($this->emails);

        if ($this->emails === []) {
            $this->emails = [app(CompanyService::class)->emptyEmailRow()];
        }
    }

    public function setPrimaryContact(int $index): void
    {
        foreach ($this->contacts as $i => $contact) {
            $this->contacts[$i]['principal'] = $i === $index;
        }
    }

    public function setPrimaryEmail(int $index): void
    {
        foreach ($this->emails as $i => $email) {
            $this->emails[$i]['principal'] = $i === $index;
        }
    }

    public function save(CompanyService $companyService): void
    {
        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'cnpj' => ['nullable', 'string', 'max:20', 'unique:companies,cnpj'.($this->editingId ? ','.$this->editingId : ''), new ValidCpfCnpj],
            'tipo' => 'required|in:propria,externa,cliente',
            'endereco' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'contacts' => 'array',
            'contacts.*.nome' => 'nullable|string|max:255',
            'contacts.*.cargo' => 'nullable|string|max:255',
            'contacts.*.telefone' => 'nullable|string|max:30',
            'contacts.*.principal' => 'boolean',
            'emails' => 'array',
            'emails.*.email' => 'nullable|email|max:255',
            'emails.*.rotulo' => 'nullable|string|max:100',
            'emails.*.principal' => 'boolean',
        ]);

        $payload = [
            'nome' => $data['nome'],
            'cnpj' => filled($data['cnpj']) ? preg_replace('/\D/', '', $data['cnpj']) : null,
            'tipo' => $data['tipo'],
            'endereco' => filled($data['endereco']) ? $data['endereco'] : null,
            'observacoes' => filled($data['observacoes']) ? $data['observacoes'] : null,
            'ativo' => $data['ativo'],
        ];

        try {
            DB::transaction(function () use ($companyService, $payload) {
                if ($this->editingId) {
                    $company = Company::findOrFail($this->editingId);
                    $this->authorize('update', $company);
                    $company->update($payload);
                } else {
                    $this->authorize('create', Company::class);
                    $company = Company::create($payload);
                }

                $companyService->syncContactsAndEmails($company, $this->contacts, $this->emails);
            });
        } catch (InvalidArgumentException $exception) {
            $this->addError('contacts', $exception->getMessage());

            return;
        }

        $this->resetForm($companyService);
        session()->flash('success', 'Empresa salva com sucesso.');
    }

    public function cancel(CompanyService $companyService): void
    {
        $this->resetForm($companyService);
    }

    public function render(): View
    {
        $companies = Company::query()
            ->with(['contacts', 'emails'])
            ->withCount('people')
            ->when($this->search, fn ($query) => $query->search($this->search))
            ->when($this->typeFilter, fn ($query) => $query->where('tipo', $this->typeFilter))
            ->orderBy('nome')
            ->paginate(20);

        return view('livewire.person.company-index', [
            'companies' => $companies,
            'companyTypes' => CompanyType::cases(),
        ]);
    }

    private function resetForm(CompanyService $companyService): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->nome = '';
        $this->cnpj = '';
        $this->tipo = CompanyType::Externa->value;
        $this->endereco = '';
        $this->observacoes = '';
        $this->ativo = true;
        $this->contacts = [$companyService->emptyContactRow()];
        $this->emails = [$companyService->emptyEmailRow()];
        $this->resetValidation();
    }
}
