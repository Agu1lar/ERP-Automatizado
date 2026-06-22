<?php

namespace App\Livewire\Logistics;

use App\Livewire\Concerns\ArchivesRecords;
use App\Models\Domain\Logistics\Yard;
use App\Support\ActiveOperatingCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class YardIndex extends Component
{
    use ArchivesRecords, AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nome = '';

    public string $cidade = '';

    public string $endereco = '';

    public string $telefone = '';

    public bool $ativo = true;

    public bool $principal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Yard::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Yard::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $yard = Yard::findOrFail($id);
        $this->authorize('update', $yard);

        $this->editingId = $yard->id;
        $this->nome = $yard->nome;
        $this->cidade = $yard->cidade ?? '';
        $this->endereco = $yard->endereco ?? '';
        $this->telefone = $yard->telefone ?? '';
        $this->ativo = $yard->ativo;
        $this->principal = $yard->principal;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'nome' => 'required|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:500',
            'telefone' => 'nullable|string|max:30',
            'ativo' => 'boolean',
            'principal' => 'boolean',
        ]);

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                $yard = Yard::findOrFail($this->editingId);
                $this->authorize('update', $yard);
                $yard->update($data);
            } else {
                $this->authorize('create', Yard::class);
                $yard = Yard::create($data);
            }

            if ($data['principal']) {
                Yard::query()
                    ->where('operating_company_id', ActiveOperatingCompany::id())
                    ->where('id', '!=', $yard->id)
                    ->update(['principal' => false]);
            }
        });

        $this->resetForm();
        session()->flash('success', 'Pátio salvo com sucesso.');
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
        $this->cidade = '';
        $this->endereco = '';
        $this->telefone = '';
        $this->ativo = true;
        $this->principal = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $term = trim($this->search);

        $yards = $this->archivableQuery(Yard::class)
            ->withCount('assets')
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('nome', 'like', $like)
                        ->orWhere('cidade', 'like', $like)
                        ->orWhere('endereco', 'like', $like);
                });
            })
            ->orderByDesc('principal')
            ->orderBy('nome')
            ->paginate(20);

        return view('livewire.logistics.yard-index', [
            'yards' => $yards,
        ]);
    }
}
