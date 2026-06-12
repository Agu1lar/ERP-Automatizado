<?php

namespace App\Livewire\Finance;

use App\Models\Domain\Finance\ReceivableTitle;
use App\Support\CashFlowQuery;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CashFlowIndex extends Component
{
    use AuthorizesRequests;

    public string $date_from = '';

    public string $date_to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ReceivableTitle::class);
        $this->date_from = now()->toDateString();
        $this->date_to = now()->addDays(30)->toDateString();
    }

    public function render(): View
    {
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->endOfDay();
        $query = app(CashFlowQuery::class);

        return view('livewire.finance.cash-flow-index', [
            'inflowRows' => $query->expectedInflows($from, $to),
            'outflowRows' => $query->expectedOutflows($from, $to),
            'totalInflows' => $query->totalExpected($from, $to),
            'totalOutflows' => $query->totalExpectedOutflows($from, $to),
        ]);
    }
}
