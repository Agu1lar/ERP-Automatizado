<?php

namespace App\Livewire\Admin;

use App\Models\Domain\Agent\AgentCommandLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AgentLogIndex extends Component
{
    use WithPagination;

    public string $commandFilter = '';

    public string $userFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $dryRunOnly = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);

        $this->dateFrom = now()->toDateString();
    }

    public function updatedCommandFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedDryRunOnly(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AgentCommandLog::query()
            ->with(['user', 'session', 'operatingCompany'])
            ->when($this->commandFilter, fn ($q) => $q->where('command', $this->commandFilter))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->dryRunOnly, fn ($q) => $q->where('dry_run', true))
            ->orderByDesc('created_at')
            ->paginate(30);

        $commands = AgentCommandLog::query()
            ->select('command')
            ->distinct()
            ->orderBy('command')
            ->pluck('command');

        $users = User::orderBy('name')->get();

        $todayCount = AgentCommandLog::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('dry_run', false)
            ->count();

        $todayDryRun = AgentCommandLog::query()
            ->whereDate('created_at', now()->toDateString())
            ->where('dry_run', true)
            ->count();

        return view('livewire.admin.agent-log-index', compact('logs', 'commands', 'users', 'todayCount', 'todayDryRun'));
    }
}
