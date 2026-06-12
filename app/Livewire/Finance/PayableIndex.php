<?php

namespace App\Livewire\Finance;

use App\Enums\PayableTitleOrigin;
use App\Enums\PayableTitleStatus;
use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\PayableTitle;
use App\Models\Domain\Person\Company;
use App\Services\PayableTitleService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PayableIndex extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $statusFilter = '';

    public string $originFilter = '';

    public string $overdueOnly = '';

    public bool $showPayModal = false;

    public ?int $payingId = null;

    public string $pay_method = PaymentMethod::Pix->value;

    public string $pay_observacoes = '';

    public string $pay_pago_em = '';

    public bool $showCreateModal = false;

    public string $create_company_id = '';

    public string $create_origem = PayableTitleOrigin::Manual->value;

    public string $create_valor = '';

    public string $create_vencimento = '';

    public string $create_observacoes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PayableTitle::class);
        $this->pay_pago_em = now()->toDateString();
        $this->create_vencimento = now()->addDays(15)->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOriginFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', PayableTitle::class);
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function saveCreate(PayableTitleService $service): void
    {
        $this->authorize('create', PayableTitle::class);

        $data = $this->validate([
            'create_company_id' => 'required|exists:companies,id',
            'create_origem' => 'required|in:'.implode(',', array_column(PayableTitleOrigin::cases(), 'value')),
            'create_valor' => 'required|numeric|min:0.01',
            'create_vencimento' => 'required|date',
            'create_observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $service->createManual(
                Company::findOrFail($data['create_company_id']),
                (float) $data['create_valor'],
                \Carbon\Carbon::parse($data['create_vencimento']),
                PayableTitleOrigin::from($data['create_origem']),
                $data['create_observacoes'] ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('create_valor', $e->getMessage());

            return;
        }

        $this->showCreateModal = false;
        session()->flash('success', 'Conta a pagar registrada.');
    }

    public function cancelCreate(): void
    {
        $this->showCreateModal = false;
    }

    public function openPayModal(int $id): void
    {
        $title = PayableTitle::findOrFail($id);
        $this->authorize('markPaid', $title);
        $this->payingId = $id;
        $this->pay_method = PaymentMethod::Pix->value;
        $this->pay_observacoes = '';
        $this->pay_pago_em = now()->toDateString();
        $this->showPayModal = true;
    }

    public function confirmPayment(PayableTitleService $service): void
    {
        $title = PayableTitle::findOrFail($this->payingId);
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

    public function cancelTitle(int $id, PayableTitleService $service): void
    {
        $title = PayableTitle::findOrFail($id);
        $this->authorize('update', $title);

        $service->cancel($title, 'Cancelado pelo usuário.');
        session()->flash('success', "Título {$title->codigo} cancelado.");
    }

    public function render(PayableTitleService $service): View
    {
        $titles = PayableTitle::query()
            ->with(['company', 'partPurchaseOrder', 'maintenanceOrder'])
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('codigo', 'like', $term)
                        ->orWhereHas('company', fn ($q) => $q->where('nome', 'like', $term))
                        ->orWhereHas('maintenanceOrder', fn ($q) => $q->where('codigo', 'like', $term))
                        ->orWhereHas('partPurchaseOrder', fn ($q) => $q->where('codigo', 'like', $term));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->originFilter, fn ($q) => $q->where('origem', $this->originFilter))
            ->when($this->overdueOnly === '1', fn ($q) => $q->overdue())
            ->orderBy('vencimento')
            ->paginate(25);

        $payingTitle = $this->payingId ? PayableTitle::find($this->payingId) : null;

        return view('livewire.finance.payable-index', [
            'titles' => $titles,
            'statusOptions' => PayableTitleStatus::cases(),
            'originOptions' => PayableTitleOrigin::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'payingTitle' => $payingTitle,
            'supplierOptions' => $service->supplierOptions(
                PayableTitleOrigin::tryFrom($this->create_origem) ?? PayableTitleOrigin::Manual
            ),
            'openBalance' => $service->openBalance(),
        ]);
    }

    private function resetCreateForm(): void
    {
        $this->create_company_id = '';
        $this->create_origem = PayableTitleOrigin::Manual->value;
        $this->create_valor = '';
        $this->create_vencimento = now()->addDays(15)->toDateString();
        $this->create_observacoes = '';
    }
}
