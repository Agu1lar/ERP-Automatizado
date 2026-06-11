<?php

namespace App\Livewire\Person;

use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\Person;
use App\Rules\ValidCpf;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PersonShow extends Component
{
    use AuthorizesRequests;

    public Person $person;

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

    public string $ativo = '1';

    public function mount(Person $person): void
    {
        $this->authorize('view', $person);
        $this->person = $person->load(['company', 'createdByUser']);
        $this->syncFields();
    }

    public function save(): void
    {
        $this->authorize('update', $this->person);

        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'cpf' => ['required', 'string', 'max:14', 'unique:people,cpf,'.$this->person->id, new ValidCpf],
            'data_nascimento' => 'nullable|date',
            'telefone' => 'nullable|string|max:30',
            'telefone_secundario' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'endereco_residencial' => 'nullable|string',
            'endereco_comercial' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'ativo' => 'required|in:0,1',
        ]);

        $this->person->update([
            'nome' => $data['nome'],
            'cpf' => preg_replace('/\D/', '', $data['cpf']),
            'data_nascimento' => filled($data['data_nascimento']) ? $data['data_nascimento'] : null,
            'telefone' => filled($data['telefone']) ? $data['telefone'] : null,
            'telefone_secundario' => filled($data['telefone_secundario']) ? $data['telefone_secundario'] : null,
            'email' => filled($data['email']) ? $data['email'] : null,
            'cargo' => filled($data['cargo']) ? $data['cargo'] : null,
            'company_id' => $data['company_id'] ?: null,
            'endereco_residencial' => filled($data['endereco_residencial']) ? $data['endereco_residencial'] : null,
            'endereco_comercial' => filled($data['endereco_comercial']) ? $data['endereco_comercial'] : null,
            'observacoes' => filled($data['observacoes']) ? $data['observacoes'] : null,
            'ativo' => $data['ativo'] === '1',
        ]);

        $this->person->refresh()->load(['company', 'createdByUser']);
        $this->syncFields();
        session()->flash('success', 'Dados atualizados.');
    }

    public function render(): View
    {
        return view('livewire.person.person-show', [
            'companies' => Company::query()->where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    private function syncFields(): void
    {
        $this->nome = $this->person->nome;
        $this->cpf = $this->person->formattedCpf();
        $this->data_nascimento = $this->person->data_nascimento?->format('Y-m-d') ?? '';
        $this->telefone = $this->person->telefone ?? '';
        $this->telefone_secundario = $this->person->telefone_secundario ?? '';
        $this->email = $this->person->email ?? '';
        $this->cargo = $this->person->cargo ?? '';
        $this->company_id = $this->person->company_id;
        $this->endereco_residencial = $this->person->endereco_residencial ?? '';
        $this->endereco_comercial = $this->person->endereco_comercial ?? '';
        $this->observacoes = $this->person->observacoes ?? '';
        $this->ativo = $this->person->ativo ? '1' : '0';
    }
}
