<?php

namespace App\Livewire\Admin;

use App\Models\Domain\Audit\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $entidadeFilter = '';

    public string $userFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatedEntidadeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->entidadeFilter, fn ($q) => $q->where('entidade', $this->entidadeFilter))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(25);

        $entities = AuditLog::query()->distinct()->orderBy('entidade')->pluck('entidade');
        $users = User::orderBy('name')->get();

        return view('livewire.admin.audit-index', compact('logs', 'entities', 'users'));
    }
}
