<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableTitleStatus;
use App\Models\Domain\Finance\ReceivableTitle;
use App\Services\Payment\AsaasPaymentGateway;
use App\Services\Payment\MockPaymentGateway;
use InvalidArgumentException;

class ReceivablePaymentService
{
    public function __construct(
        private readonly ReceivableTitleService $receivableTitleService,
    ) {}

    public function gateway(): PaymentGateway
    {
        return match (config('payment.driver')) {
            'asaas' => app(AsaasPaymentGateway::class),
            default => app(MockPaymentGateway::class),
        };
    }

    public function createCharge(ReceivableTitle $title, PaymentMethod $method): ReceivableTitle
    {
        if ($title->statusEnum() !== ReceivableTitleStatus::Aberto) {
            throw new InvalidArgumentException('Somente títulos em aberto podem gerar cobrança.');
        }

        if (! in_array($method, [PaymentMethod::Pix, PaymentMethod::Boleto], true)) {
            throw new InvalidArgumentException('Use PIX ou boleto para cobrança automática.');
        }

        $result = $this->gateway()->createCharge($title, $method);

        $title->update([
            'gateway_driver' => config('payment.driver'),
            'gateway_charge_id' => $result->chargeId,
            'gateway_status' => $result->status->value,
            'gateway_billing_type' => $method->value,
            'pix_qr_code' => $result->pixQrCode,
            'pix_qr_image_url' => $result->pixQrImageUrl,
            'boleto_url' => $result->boletoUrl,
            'gateway_invoice_url' => $result->invoiceUrl,
            'gateway_charge_created_at' => now(),
        ]);

        return $title->fresh();
    }

    public function refreshCharge(ReceivableTitle $title): ReceivableTitle
    {
        if (! $title->gateway_charge_id) {
            throw new InvalidArgumentException('Título sem cobrança no gateway.');
        }

        $result = $this->gateway()->refreshCharge($title);

        $title->update([
            'gateway_status' => $result->status->value,
            'pix_qr_code' => $result->pixQrCode ?? $title->pix_qr_code,
            'pix_qr_image_url' => $result->pixQrImageUrl ?? $title->pix_qr_image_url,
            'boleto_url' => $result->boletoUrl ?? $title->boleto_url,
            'gateway_invoice_url' => $result->invoiceUrl ?? $title->gateway_invoice_url,
        ]);

        if (in_array($result->status, [PaymentChargeStatus::Received, PaymentChargeStatus::Confirmed], true)
            && $title->statusEnum() === ReceivableTitleStatus::Aberto) {
            $this->receivableTitleService->markAsPaid(
                $title->fresh(),
                PaymentMethod::from($title->gateway_billing_type ?? PaymentMethod::Pix->value),
                'Baixa automática via gateway ('.config('payment.driver').').',
                now(),
            );
        }

        return $title->fresh();
    }

    public function handleWebhookPayment(string $chargeId, string $event, ?float $value = null): ?ReceivableTitle
    {
        $title = ReceivableTitle::query()
            ->where('gateway_charge_id', $chargeId)
            ->first();

        if (! $title) {
            return null;
        }

        $paidEvents = ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED_IN_CASH'];

        if (in_array(strtoupper($event), $paidEvents, true)
            && $title->statusEnum() === ReceivableTitleStatus::Aberto) {
            $method = PaymentMethod::from($title->gateway_billing_type ?? PaymentMethod::Pix->value);

            $this->receivableTitleService->markAsPaid(
                $title,
                $method,
                'Webhook '.$event.($value ? ' — R$ '.number_format($value, 2, ',', '.') : ''),
                now(),
            );

            $title->update(['gateway_status' => PaymentChargeStatus::Received->value]);
        }

        return $title?->fresh();
    }
}
