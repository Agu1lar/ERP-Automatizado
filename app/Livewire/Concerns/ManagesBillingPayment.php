<?php

namespace App\Livewire\Concerns;

use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Services\ReceivableTitleService;

trait ManagesBillingPayment
{
    public bool $showBillingPayModal = false;

    public ?int $billingPayTitleId = null;

    public string $billing_pay_method = '';

    public string $billing_pay_observacoes = '';

    public string $billing_pay_pago_em = '';

    protected function initBillingPaymentDefaults(): void
    {
        $this->billing_pay_method = PaymentMethod::Pix->value;
        $this->billing_pay_pago_em = now()->toDateString();
    }

    public function openBillingPayModal(int $titleId): void
    {
        $title = ReceivableTitle::findOrFail($titleId);
        $this->authorize('markPaid', $title);

        $this->billingPayTitleId = $titleId;
        $this->billing_pay_method = PaymentMethod::Pix->value;
        $this->billing_pay_observacoes = '';
        $this->billing_pay_pago_em = now()->toDateString();
        $this->showBillingPayModal = true;
    }

    public function confirmBillingPayment(ReceivableTitleService $service): void
    {
        $title = ReceivableTitle::findOrFail($this->billingPayTitleId);
        $this->authorize('markPaid', $title);

        $data = $this->validate([
            'billing_pay_method' => 'required|in:'.implode(',', array_column(PaymentMethod::cases(), 'value')),
            'billing_pay_observacoes' => 'nullable|string|max:1000',
            'billing_pay_pago_em' => 'required|date',
        ]);

        try {
            $service->markAsPaid(
                $title,
                PaymentMethod::from($data['billing_pay_method']),
                $data['billing_pay_observacoes'] ?: null,
                \Carbon\Carbon::parse($data['billing_pay_pago_em']),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('billing_pay', $e->getMessage());

            return;
        }

        $this->showBillingPayModal = false;
        $this->billingPayTitleId = null;
        session()->flash('success', "Pagamento registrado — {$title->codigo}.");
        $this->afterBillingPaymentConfirmed($title->fresh());
    }

    public function cancelBillingPayment(): void
    {
        $this->showBillingPayModal = false;
        $this->billingPayTitleId = null;
    }

    protected function afterBillingPaymentConfirmed(ReceivableTitle $title): void
    {
        // Hook para componentes que precisam recarregar dados.
    }
}
