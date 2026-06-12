<?php

namespace App\Data;

use App\Enums\PaymentChargeStatus;

readonly class PaymentChargeResult
{
    public function __construct(
        public string $chargeId,
        public PaymentChargeStatus $status,
        public ?string $pixQrCode = null,
        public ?string $pixQrImageUrl = null,
        public ?string $boletoUrl = null,
        public ?string $invoiceUrl = null,
    ) {}
}
