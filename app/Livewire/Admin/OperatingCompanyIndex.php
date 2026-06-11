<?php

namespace App\Livewire\Admin;

use App\Models\Domain\Organization\OperatingCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class OperatingCompanyIndex extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public ?int $editingId = null;

    public string $nome = '';

    public string $razao_social = '';

    public string $cnpj = '';

    public string $endereco = '';

    public string $telefone = '';

    public string $email = '';

    public string $logo_path = '';

    public bool $ativo = true;

    public $logo_upload;

    public function mount(): void
    {
        $this->authorize('viewAny', OperatingCompany::class);
    }

    public function edit(int $id): void
    {
        $company = OperatingCompany::query()->findOrFail($id);
        $this->authorize('update', $company);

        $this->editingId = $company->id;
        $this->nome = $company->nome;
        $this->razao_social = $company->razao_social ?? '';
        $this->cnpj = $company->formattedCnpj() ?? (string) $company->cnpj;
        $this->endereco = $company->endereco ?? '';
        $this->telefone = $company->telefone ?? '';
        $this->email = $company->email ?? '';
        $this->logo_path = $company->logo_path ?? '';
        $this->ativo = $company->ativo;
        $this->logo_upload = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $company = OperatingCompany::query()->findOrFail($this->editingId);
        $this->authorize('update', $company);

        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'endereco' => 'nullable|string|max:500',
            'telefone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'logo_path' => 'nullable|string|max:500',
            'ativo' => 'boolean',
            'logo_upload' => 'nullable|image|max:2048',
        ]);

        $logoPath = $data['logo_path'] ?: null;

        if ($this->logo_upload) {
            $stored = $this->logo_upload->store('operating-companies', 'public');
            $logoPath = 'storage/'.$stored;
        }

        $company->update([
            'nome' => $data['nome'],
            'razao_social' => $data['razao_social'] ?: null,
            'cnpj' => preg_replace('/\D/', '', $data['cnpj'] ?? '') ?: null,
            'endereco' => $data['endereco'] ?: null,
            'telefone' => $data['telefone'] ?: null,
            'email' => $data['email'] ?: null,
            'logo_path' => $logoPath,
            'ativo' => $data['ativo'],
        ]);

        $this->cancel();
        session()->flash('success', "Empresa {$company->nome} atualizada. Contratos e exportações usarão estes dados.");
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->nome = '';
        $this->razao_social = '';
        $this->cnpj = '';
        $this->endereco = '';
        $this->telefone = '';
        $this->email = '';
        $this->logo_path = '';
        $this->ativo = true;
        $this->logo_upload = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.operating-company-index', [
            'companies' => OperatingCompany::query()->orderBy('id')->get(),
        ]);
    }
}
