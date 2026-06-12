<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentChargeResult;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Domain\Finance\ReceivableTitle;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGateway
{
    public function createCharge(ReceivableTitle $title, PaymentMethod $method): PaymentChargeResult
    {
        $chargeId = 'mock_'.Str::lower(Str::random(12));

        return match ($method) {
            PaymentMethod::Pix => new PaymentChargeResult(
                chargeId: $chargeId,
                status: PaymentChargeStatus::Pending,
                pixQrCode: '00020126580014br.gov.bcb.pix0136'.$chargeId.'520400005303986540'.sprintf('%0.2f', $title->valor).'5802BR5925Gestao Acesso Mock6009SAO PAULO62070503***6304ABCD',
                pixQrImageUrl: 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=mock-'.$chargeId,
            ),
            PaymentMethod::Boleto => new PaymentChargeResult(
                chargeId: $chargeId,
                status: PaymentChargeStatus::Pending,
                boletoUrl: 'https://sandbox.asaas.com/b/pdf/'.$chargeId,
                invoiceUrl: 'https://sandbox.asaas.com/i/'.$chargeId,
            ),
            default => throw new \InvalidArgumentException('Forma de pagamento não suportada pelo gateway.'),
        };
    }

    public function refreshCharge(ReceivableTitle $title): PaymentChargeResult
    {
        if (! $title->gateway_charge_id) {
            throw new \InvalidArgumentException('Título sem cobrança no gateway.');
        }

        return new PaymentChargeResult(
            chargeId: $title->gateway_charge_id,
            status: PaymentChargeStatus::from($title->gateway_status ?? PaymentChargeStatus::Pending->value),
            pixQrCode: $title->pix_qr_code,
            pixQrImageUrl: $title->pix_qr_image_url,
            boletoUrl: $title->boleto_url,
            invoiceUrl: $title->gateway_invoice_url,
        );
    }
}
