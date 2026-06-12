<?php

namespace App\Livewire\Admin;

use App\Models\Domain\Organization\OperatingCompany;
use App\Models\User;
use App\Services\AgentCopilotMetricsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AgentMetricsIndex extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public string $userFilter = '';

    public string $operatingCompanyFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function render(): View
    {
        $service = app(AgentCopilotMetricsService::class);

        $metrics = $service->summary(
            \Carbon\Carbon::parse($this->dateFrom)->startOfDay(),
            \Carbon\Carbon::parse($this->dateTo)->endOfDay(),
            operatingCompanyId: $this->operatingCompanyFilter !== '' ? (int) $this->operatingCompanyFilter : null,
            userId: $this->userFilter !== '' ? (int) $this->userFilter : null,
        );

        return view('livewire.admin.agent-metrics-index', [
            'metrics' => $metrics,
            'users' => User::orderBy('name')->get(),
            'operatingCompanies' => OperatingCompany::orderBy('nome')->get(),
        ]);
    }
}
