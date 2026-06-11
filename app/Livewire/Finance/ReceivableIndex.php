<?php

namespace App\Livewire\Finance;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Services\AccountingExportService;
use App\Services\ReceivableTitleService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ReceivableIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $statusFilter = '';

    public string $overdueOnly = '';

    public string $notExportedOnly = '';

    public bool $showPayModal = false;

    public ?int $payingId = null;

    public string $pay_method = PaymentMethod::Pix->value;

    public string $pay_observacoes = '';

    public string $pay_pago_em = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ReceivableTitle::class);
        $this->pay_pago_em = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function updatedNotExportedOnly(): void
    {
        $this->resetPage();
    }

    public function markExportedToErp(int $id, AccountingExportService $exportService): void
    {
        $title = ReceivableTitle::query()->findOrFail($id);
        $this->authorize('update', $title);

        $exportService->markSingleExported($title, 'manual');
        session()->flash('success', "Título {$title->codigo} marcado como exportado para ERP.");
    }

    public function clearExportedFlag(int $id, AccountingExportService $exportService): void
    {
        $title = ReceivableTitle::query()->findOrFail($id);
        $this->authorize('update', $title);

        $exportService->clearExportedFlag($title);
        session()->flash('success', "Marca de exportação removida — {$title->codigo}.");
    }

    public function openPayModal(int $id): void
    {
        $title = ReceivableTitle::findOrFail($id);
        $this->authorize('markPaid', $title);
        $this->payingId = $id;
        $this->pay_method = PaymentMethod::Pix->value;
        $this->pay_observacoes = '';
        $this->pay_pago_em = now()->toDateString();
        $this->showPayModal = true;
    }

    public function confirmPayment(ReceivableTitleService $service): void
    {
        $title = ReceivableTitle::findOrFail($this->payingId);
        $this->authorize('markPaid', $title);

        $data = $this->validate([
            'pay_method' => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'pay_observacoes' => 'nullable|string|max:1000',
            'pay_pago_em' => 'required|date',
        ]);

        try {
            $service->markAsPaid(
                $title,
                PaymentMethod::from($data['pay_method']),
                $data['pay_observacoes'] ?: null,
                \Carbon\Carbon::parse($data['pay_pago_em']),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('pay', $e->getMessage());

            return;
        }

        $this->showPayModal = false;
        $this->payingId = null;
        session()->flash('success', "Pagamento registrado — {$title->codigo}.");
    }

    public function cancelPayment(): void
    {
        $this->showPayModal = false;
        $this->payingId = null;
    }

    public function render(): View
    {
        $titles = ReceivableTitle::query()
            ->with(['customer', 'rental'])
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('codigo', 'like', $term)
                        ->orWhereHas('customer', fn ($q) => $q->where('nome', 'like', $term))
                        ->orWhereHas('rental', fn ($q) => $q->where('codigo', 'like', $term));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->overdueOnly === '1', fn ($q) => $q->overdue())
            ->when($this->notExportedOnly === '1', fn ($q) => $q->notExportedToErp())
            ->orderBy('vencimento')
            ->paginate(25);

        $payingTitle = $this->payingId ? ReceivableTitle::find($this->payingId) : null;

        return view('livewire.finance.receivable-index', [
            'titles' => $titles,
            'statusOptions' => ReceivableTitleStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'payingTitle' => $payingTitle,
        ]);
    }
}
