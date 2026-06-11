<?php

namespace App\Livewire\Finance;

use App\Enums\PaymentMethod;
use App\Enums\RentalBillingQueueStatus;
use App\Livewire\Concerns\ManagesBillingPayment;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Models\Domain\Rental\RentalBillingQueueEntry;
use App\Services\RentalBillingService;
use App\Support\BillingQueueReportQuery;
use App\Support\FlashMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class BillingQueueIndex extends Component
{
    use AuthorizesRequests;
    use ManagesBillingPayment;

    #[Url(as: 'status')]
    public string $statusFilter = 'pendente';

    public ?int $highlightBillingEntryId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ReceivableTitle::class);
        $this->initBillingPaymentDefaults();
    }

    public function authorizeEntry(int $entryId, RentalBillingService $billingService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = RentalBillingQueueEntry::query()->findOrFail($entryId);

        try {
            $billingService->authorizeEntry($entry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $entry = $entry->fresh(['customer', 'rental', 'receivableTitle']);
        $this->highlightBillingEntryId = $entry->id;
        $this->statusFilter = RentalBillingQueueStatus::Autorizado->value;

        FlashMessage::success("Pendência {$entry->codigo} autorizada.", [
            [
                'label' => 'Ver autorizados',
                'url' => route('finance.billing-queue', ['status' => RentalBillingQueueStatus::Autorizado->value]),
                'primary' => true,
            ],
            [
                'label' => 'Abrir ficha da locação',
                'url' => route('rentals.show', $entry->rental),
            ],
        ]);
    }

    public function invoiceEntry(int $entryId, RentalBillingService $billingService): void
    {
        $this->authorize('create', ReceivableTitle::class);

        $entry = RentalBillingQueueEntry::query()->findOrFail($entryId);

        try {
            $entry = $billingService->authorizeAndInvoice($entry);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->finishInvoiceAction($entry->fresh(['customer', 'rental', 'receivableTitle']));
    }

    public function dismissBillingHighlight(): void
    {
        $this->highlightBillingEntryId = null;
    }

    protected function afterBillingPaymentConfirmed(ReceivableTitle $title): void
    {
        $entryId = RentalBillingQueueEntry::query()
            ->where('receivable_title_id', $title->id)
            ->value('id');

        if ($entryId) {
            $this->highlightBillingEntryId = (int) $entryId;
        }
    }

    private function finishInvoiceAction(RentalBillingQueueEntry $entry): void
    {
        $this->highlightBillingEntryId = $entry->id;
        $this->statusFilter = RentalBillingQueueStatus::Faturado->value;

        FlashMessage::success("Fatura {$entry->codigo} gerada e título criado.", [
            [
                'label' => 'Ver faturamentos',
                'url' => route('finance.billing-queue', ['status' => RentalBillingQueueStatus::Faturado->value]),
                'primary' => true,
            ],
            [
                'label' => 'Títulos a receber',
                'url' => route('finance.receivables', ['q' => $entry->receivableTitle?->codigo ?? '']),
            ],
        ]);

        $this->dispatch('billing-download', url: route('finance.billing.pdf', $entry));
    }

    /** @return array<string, int> */
    private function statusCounts(): array
    {
        $rows = RentalBillingQueueEntry::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pendente' => (int) ($rows[RentalBillingQueueStatus::Pendente->value] ?? 0),
            'autorizado' => (int) ($rows[RentalBillingQueueStatus::Autorizado->value] ?? 0),
            'faturado' => (int) ($rows[RentalBillingQueueStatus::Faturado->value] ?? 0),
            'cancelado' => (int) ($rows[RentalBillingQueueStatus::Cancelado->value] ?? 0),
        ];
    }

    public function render(): View
    {
        $status = $this->statusFilter !== '' ? $this->statusFilter : null;

        if ($status === 'pendente') {
            $status = null;
            $entries = RentalBillingQueueEntry::query()
                ->with(['customer', 'rental', 'receivableTitle'])
                ->pendingInvoice()
                ->latest('gerado_em')
                ->get();
        } else {
            $entries = RentalBillingQueueEntry::query()
                ->with(['customer', 'rental', 'receivableTitle'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('gerado_em')
                ->limit(200)
                ->get();
        }

        $report = app(BillingQueueReportQuery::class)->summary(
            $this->statusFilter === 'pendente' ? null : ($status ?: null)
        );

        if ($this->statusFilter === 'pendente') {
            $report = [
                'total_nf' => round((float) $entries->sum('valor_nf'), 2),
                'total_car' => round((float) $entries->sum('valor_car'), 2),
                'total_registros' => $entries->count(),
                'grupos' => $entries->groupBy('tipo')->map(function ($group, $tipo) {
                    $type = \App\Enums\RentalBillingQueueType::from($tipo);

                    return [
                        'tipo' => $type,
                        'label' => $type->label(),
                        'registros' => $group->count(),
                        'total_nf' => round((float) $group->sum('valor_nf'), 2),
                        'total_car' => round((float) $group->sum('valor_car'), 2),
                        'entries' => $group,
                    ];
                })->values(),
            ];
        }

        $highlightBillingEntry = $this->highlightBillingEntryId
            ? RentalBillingQueueEntry::query()
                ->with(['customer', 'rental', 'receivableTitle'])
                ->find($this->highlightBillingEntryId)
            : null;

        $billingPayTitle = $this->billingPayTitleId
            ? ReceivableTitle::find($this->billingPayTitleId)
            : null;

        $statusCounts = $this->statusCounts();

        return view('livewire.finance.billing-queue-index', [
            'entries' => $entries,
            'report' => $report,
            'statusOptions' => RentalBillingQueueStatus::cases(),
            'statusCounts' => $statusCounts,
            'pendingCount' => app(BillingQueueReportQuery::class)->pendingCount(),
            'highlightBillingEntry' => $highlightBillingEntry,
            'billingPayTitle' => $billingPayTitle,
            'paymentMethods' => PaymentMethod::cases(),
            'billingInvoiceMethod' => 'invoiceEntry',
            'billingShowQueueNav' => true,
        ]);
    }
}
